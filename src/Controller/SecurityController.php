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

class SecurityController extends AbstractController
{
    #[Route(path: '/login', name: 'app_login')]
    public function login(AuthenticationUtils $authenticationUtils): Response
    {
        // if ($this->getUser()) {
        //     return $this->redirectToRoute('target_path');
        // }

        // get the login error if there is one
        $error = $authenticationUtils->getLastAuthenticationError();
        // last username entered by the user
        $lastUsername = $authenticationUtils->getLastUsername();

        return $this->render('security/login.html.twig', ['last_username' => $lastUsername, 'error' => $error]);
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
        // ensure default role is ROLE_USER (the entity constructor already sets this)
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
}
