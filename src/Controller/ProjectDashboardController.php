<?php

namespace App\Controller;

use App\Entity\Project;
use App\Repository\ContributionRepository;
use App\Repository\ProjectRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_CREATOR')]
final class ProjectDashboardController extends AbstractController
{
    #[Route('/dashboard/project/{id}', name: 'app_dashboard_project')]
    public function projectDashboard(Project $project, ContributionRepository $contributionRepo, ProjectRepository $projectRepo): Response
    {
        if ($this->getUser() !== $project->getCreator()) {
            throw $this->createAccessDeniedException("You are not the owner of this project.");
        }

        $userProjects = $projectRepo->findBy(['creator' => $project->getCreator()]);

        $paidContributions = $contributionRepo->findBy(
            ['project' => $project, 'paymentStatus' => 'paid'],
            ['createdAt' => 'ASC']
        );

        $chartData = [];
        $cumulative = 0;
        foreach ($paidContributions as $contribution) {
            $date = $contribution->getCreatedAt()->format('M d');
            if (!isset($chartData[$date])) {
                $chartData[$date] = 0;
            }

            $cumulative += $contribution->getAmount();
            $chartData[$date] = $cumulative;
        }

        return $this->render('project_dashboard/index.html.twig', [
            'project' => $project,
            'userProjects' => $userProjects,
            'backerCount' => count($paidContributions),
            'chartLabels' => json_encode(array_keys($chartData)),
            'chartValues' => json_encode(array_values($chartData)),
            'recentContributions' => array_reverse(array_slice($paidContributions, -5)),
        ]);
    }

    #[Route('/dashboard/project/{id}/request-payout', name: 'app_project_request_payout', methods: ['POST'])]
    public function requestPayout(Project $project, Request $request, EntityManagerInterface $em): Response
    {
        if ($this->getUser() !== $project->getCreator()) {
            throw $this->createAccessDeniedException("You are not the owner of this project.");
        }

        if ($project->getCollectedAmount() < (float)$project->getGoalAmount()) {
            $this->addFlash('error', 'Goal not reached yet. Cannot request payout.');
            return $this->redirectToRoute('app_dashboard_project', ['id' => $project->getId()]);
        }

        if (in_array($project->getPayoutStatus(), ['requested', 'paid'])) {
            $this->addFlash('error', 'Payout already requested or completed.');
            return $this->redirectToRoute('app_dashboard_project', ['id' => $project->getId()]);
        }

        $rib = $request->request->get('rib');
        if (empty($rib)) {
            $this->addFlash('error', 'Please provide a valid bank account number (RIB).');
            return $this->redirectToRoute('app_dashboard_project', ['id' => $project->getId()]);
        }

        $project->setRib($rib);
        $project->setPayoutStatus('requested');
        $project->setPayoutRequestedAt(new \DateTime());

        $em->flush();

        $this->addFlash('success', 'Payout request submitted successfully. We will process it soon.');
        return $this->redirectToRoute('app_dashboard_project', ['id' => $project->getId()]);
    }

    #[Route('/dashboard/project/{id}/transactions', name: 'app_project_transactions')]
    public function transactions(Project $project, ContributionRepository $contributionRepo): Response
    {
        if ($this->getUser() !== $project->getCreator()) {
            throw $this->createAccessDeniedException("You are not the owner of this project.");
        }

        $contributions = $contributionRepo->findBy(
            ['project' => $project],
            ['createdAt' => 'DESC']
        );

        return $this->render('project_dashboard/transactions.html.twig', [
            'project' => $project,
            'contributions' => $contributions,
        ]);
    }
}
