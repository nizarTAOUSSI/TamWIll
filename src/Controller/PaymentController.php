<?php

namespace App\Controller;

use App\Entity\Contribution;
use App\Entity\Payment;
use App\Entity\Project;
use Doctrine\ORM\EntityManagerInterface;
use Stripe\Checkout\Session;
use Stripe\Stripe;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class PaymentController extends AbstractController
{
    #[Route('/payment/checkout/{id}', name: 'app_payment_checkout', methods: ['POST'])]
    public function checkout(Project $project, Request $request, EntityManagerInterface $em): Response
    {
        $user = $this->getUser();
        if (!$user) return $this->redirectToRoute('app_login', ['login' => 'true']);

        $amount = $request->request->get('amount');
        $isAnonymous = $request->request->has('is_anonymous');

        if ($amount < 5) {
            $this->addFlash('error', 'Minimum donation is $5.');
            return $this->redirectToRoute('app_project_show', ['id' => $project->getId()]);
        }

        $contribution = new Contribution();
        $contribution->setUser($user);
        $contribution->setProject($project);
        $contribution->setAmount($amount);
        $contribution->setIsAnonymous($isAnonymous);
        $contribution->setPaymentStatus('pending');

        $em->persist($contribution);
        $em->flush();

        \Stripe\Stripe::setApiKey($_ENV['STRIPE_SECRET_KEY']);

        $paymentIntent = \Stripe\PaymentIntent::create([
            'amount' => $amount * 100,
            'currency' => 'usd',
            'automatic_payment_methods' => ['enabled' => true],
            'metadata' => [
                'contribution_id' => $contribution->getId(),
                'project_id' => $project->getId()
            ],
        ]);

        return $this->render('payment/checkout.html.twig', [
            'clientSecret' => $paymentIntent->client_secret,
            'stripePublicKey' => $_ENV['STRIPE_PUBLIC_KEY'],
            'project' => $project,
            'amount' => $amount,
            'contribution' => $contribution
        ]);
    }

    #[Route('/payment/success/{contributionId}', name: 'app_payment_success')]
    public function success(int $contributionId, Request $request, EntityManagerInterface $em): Response
    {
        $contribution = $em->getRepository(Contribution::class)->find($contributionId);

        $paymentIntentId = $request->query->get('payment_intent');

        if (!$contribution || !$paymentIntentId) {
            return $this->redirectToRoute('app_project_index');
        }

        \Stripe\Stripe::setApiKey($_ENV['STRIPE_SECRET_KEY']);
        $intent = \Stripe\PaymentIntent::retrieve($paymentIntentId);

        if ($intent->status === 'succeeded') {
            if ($contribution->getPaymentStatus() !== 'paid') {
                $contribution->setPaymentStatus('paid');

                $payment = new Payment();
                $payment->setProvider('Stripe Elements');
                $payment->setTransactionId($intent->id);
                $payment->setAmount($contribution->getAmount());
                $payment->setStatus('completed');
                $payment->setContribution($contribution);

                $project = $contribution->getProject();
                $project->setCollectedAmount($project->getCollectedAmount() + $contribution->getAmount());

                $em->persist($payment);
                $em->flush();

                $this->addFlash('success', 'Thank you! Donation successful.');
            }
        }

        return $this->redirectToRoute('app_project_show', ['id' => $contribution->getProject()->getId()]);
    }
    #[Route('/payment/cancel/{id}', name: 'app_payment_cancel')]
    public function cancel(Contribution $contribution, EntityManagerInterface $em): Response
    {
        $contribution->setPaymentStatus('cancelled');
        $em->flush();
        $this->addFlash('error', 'Payment was cancelled.');
        return $this->redirectToRoute('app_project_show', ['id' => $contribution->getProject()->getId()]);
    }
}
