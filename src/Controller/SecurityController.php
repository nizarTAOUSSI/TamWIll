<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;
use Symfony\Component\HttpFoundation\Request;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use App\Entity\User;
use App\Repository\UserRepository;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class SecurityController extends AbstractController
{
    #[Route(path: '/', name: 'app_login')]
    public function login(AuthenticationUtils $authenticationUtils): Response
    {
        // if ($this->getUser()) {
        //     return $this->redirectToRoute('target_path');
        // }

        // get the login error if there is one
        $error = $authenticationUtils->getLastAuthenticationError();
        // last username entered by the user
        $lastUsername = $authenticationUtils->getLastUsername();

        return $this->render('/', ['last_username' => $lastUsername, 'error' => $error]);
    }

    #[Route(path: '/register', name: 'app_register', methods: ['POST'])]
    public function register(Request $request, UserPasswordHasherInterface $passwordHasher, EntityManagerInterface $em, UserRepository $userRepository): Response
    {
        $token = $request->request->get('_csrf_token');
        if (!$this->isCsrfTokenValid('register', $token)) {
            $this->addFlash('error', 'Invalid CSRF token.');
            return $this->redirectToRoute('app_login');
        }

        $name = trim((string) $request->request->get('name'));
        $email = trim((string) $request->request->get('email'));
        $plainPassword = (string) $request->request->get('password');

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->addFlash('error', 'Please provide a valid email address.');
            return $this->redirectToRoute('app_login');
        }

        if (strlen($plainPassword) < 6) {
            $this->addFlash('error', 'Password must be at least 6 characters long.');
            return $this->redirectToRoute('app_login');
        }

        // check for duplicate email
        if ($userRepository->findOneBy(['email' => $email])) {
            $this->addFlash('error', 'An account with this email already exists.');
            return $this->redirectToRoute('app_login');
        }

        $user = new User();
        $user->setName($name ?: explode('@', $email)[0]);
        $user->setEmail($email);
        $hashed = $passwordHasher->hashPassword($user, $plainPassword);
        $user->setPassword($hashed);
        $user->setRole('ROLE_USER');
        $user->setIsActive(true);

        $em->persist($user);
        $em->flush();

        $this->addFlash('success', 'Account created successfully. You can now sign in.');

        return $this->redirectToRoute('app_login');
    }

    #[Route(path: '/logout', name: 'app_logout')]
    public function logout(): void
    {
        throw new \LogicException('This method can be blank - it will be intercepted by the logout key on your firewall.');
    }

    #[Route(path: '/forgot-password', name: 'app_forgot_password', methods: ['GET', 'POST'])]
    public function forgotPassword(Request $request, UserRepository $userRepository, EntityManagerInterface $em, MailerInterface $mailer): Response
    {
        if ($request->isMethod('POST')) {
            $token = $request->request->get('_csrf_token');
            if (!$this->isCsrfTokenValid('forgot_password', $token)) {
                $this->addFlash('error', 'Invalid CSRF token.');
                return $this->redirectToRoute('app_forgot_password');
            }

            $email = trim((string) $request->request->get('email'));

            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $this->addFlash('error', 'Please provide a valid email address.');
                return $this->redirectToRoute('app_forgot_password');
            }

            $user = $userRepository->findOneBy(['email' => $email]);

            if ($user && $user->isEnabled()) {
                // Check if we recently sent a reset email (rate limiting)
                if ($user->getResetTokenExpiresAt() && $user->getResetTokenExpiresAt() > new \DateTimeImmutable('-5 minutes')) {
                    $this->addFlash('error', 'A password reset email was recently sent. Please wait 5 minutes before requesting another one.');
                    return $this->redirectToRoute('app_forgot_password');
                }

                // Generate a secure random token
                $resetToken = bin2hex(random_bytes(32));
                $expiresAt = new \DateTimeImmutable('+1 hour');

                $user->setResetToken($resetToken);
                $user->setResetTokenExpiresAt($expiresAt);
                $em->flush();

                // Send reset email
                $resetUrl = $this->generateUrl('app_reset_password', ['token' => $resetToken], UrlGeneratorInterface::ABSOLUTE_URL);

                // Debug: Log that we're about to send an email
                error_log('Attempting to send password reset email to: ' . $user->getEmail());
                error_log('Reset URL: ' . $resetUrl);

                $email = (new Email())
                    ->from('noreply@tamwill.com')
                    ->to($user->getEmail())
                    ->subject('Password Reset Request - TamWill')
                    ->html($this->renderView('emails/reset_password.html.twig', [
                        'user' => $user,
                        'resetUrl' => $resetUrl,
                        'expiresAt' => $expiresAt
                    ]));

                try {
                    $mailer->send($email);
                    $this->addFlash('success', 'If an account with that email exists, we have sent you a password reset link.');
                } catch (\Exception $e) {
                    // Log the actual error for debugging
                    error_log('Mailer error: ' . $e->getMessage());
                    $this->addFlash('error', 'There was an error sending the reset email: ' . $e->getMessage());
                }
            } else {
                // Don't reveal whether the email exists or not for security
                $this->addFlash('success', 'If an account with that email exists, we have sent you a password reset link.');
            }

            return $this->redirectToRoute('app_forgot_password');
        }

        return $this->render('security/forgot_password.html.twig');
    }

    #[Route(path: '/reset-password/{token}', name: 'app_reset_password', methods: ['GET', 'POST'])]
    public function resetPassword(string $token, Request $request, UserRepository $userRepository, UserPasswordHasherInterface $passwordHasher, EntityManagerInterface $em): Response
    {
        $user = $userRepository->findOneBy(['resetToken' => $token]);

        if (!$user || !$user->isResetTokenValid()) {
            $this->addFlash('error', 'Invalid or expired reset token.');
            return $this->redirectToRoute('app_forgot_password');
        }

        if ($request->isMethod('POST')) {
            $csrfToken = $request->request->get('_csrf_token');
            if (!$this->isCsrfTokenValid('reset_password', $csrfToken)) {
                $this->addFlash('error', 'Invalid CSRF token.');
                return $this->redirectToRoute('app_reset_password', ['token' => $token]);
            }

            $password = (string) $request->request->get('password');
            $confirmPassword = (string) $request->request->get('confirm_password');

            if (strlen($password) < 6) {
                $this->addFlash('error', 'Password must be at least 6 characters long.');
                return $this->redirectToRoute('app_reset_password', ['token' => $token]);
            }

            if ($password !== $confirmPassword) {
                $this->addFlash('error', 'Passwords do not match.');
                return $this->redirectToRoute('app_reset_password', ['token' => $token]);
            }

            // Reset password
            $hashedPassword = $passwordHasher->hashPassword($user, $password);
            $user->setPassword($hashedPassword);
            $user->setResetToken(null);
            $user->setResetTokenExpiresAt(null);

            $em->flush();

            $this->addFlash('success', 'Your password has been reset successfully. You can now sign in.');
            return $this->redirectToRoute('app_login');
        }

        return $this->render('security/reset_password.html.twig', [
            'token' => $token
        ]);
    }
}
