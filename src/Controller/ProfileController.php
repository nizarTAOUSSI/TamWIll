<?php

namespace App\Controller;

use App\Entity\User;
use App\Form\PasswordChangeType;
use App\Form\ProfileType;
use App\Repository\ContributionRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\String\Slugger\SluggerInterface;

#[IsGranted('ROLE_USER')]
class ProfileController extends AbstractController
{
    #[Route('/profile', name: 'app_profile')]
    public function index(
        Request $request,
        EntityManagerInterface $em,
        ContributionRepository $contributionRepo,
        SluggerInterface $slugger,
        UserPasswordHasherInterface $passwordHasher
    ): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        $form = $this->createForm(ProfileType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $profilePictureFile = $form->get('profilePicture')->getData();

            if ($profilePictureFile) {
                $originalFilename = pathinfo($profilePictureFile->getClientOriginalName(), PATHINFO_FILENAME);
                $safeFilename = $slugger->slug($originalFilename);
                $newFilename = $safeFilename . '-' . uniqid() . '.' . $profilePictureFile->guessExtension();

                try {
                    $profilePictureFile->move(
                        $this->getParameter('kernel.project_dir') . '/public/uploads/profiles',
                        $newFilename
                    );

                    $oldPicture = $user->getProfilePicture();
                    if ($oldPicture) {
                        $oldPath = $this->getParameter('kernel.project_dir') . '/public/uploads/' . $oldPicture;
                        if (file_exists($oldPath)) {
                            unlink($oldPath);
                        }
                    }

                    $user->setProfilePicture('profiles/' . $newFilename);
                } catch (FileException $e) {
                    $this->addFlash('error', 'Failed to upload profile picture.');
                    return $this->redirectToRoute('app_profile');
                }
            }

            $em->flush();
            $this->addFlash('success', 'Profile updated successfully!');
            return $this->redirectToRoute('app_profile');
        }

        $passwordForm = $this->createForm(PasswordChangeType::class);
        $passwordForm->handleRequest($request);

        if ($passwordForm->isSubmitted() && $passwordForm->isValid()) {
            $currentPassword = $passwordForm->get('currentPassword')->getData();

            if (!$passwordHasher->isPasswordValid($user, $currentPassword)) {
                $this->addFlash('error', 'Current password is incorrect.');
                return $this->redirectToRoute('app_profile');
            }

            $newPassword = $passwordForm->get('newPassword')->getData();
            $hashedPassword = $passwordHasher->hashPassword($user, $newPassword);
            $user->setPassword($hashedPassword);

            $em->flush();
            $this->addFlash('success', 'Password updated successfully!');
            return $this->redirectToRoute('app_profile');
        }

        $donations = $contributionRepo->findBy(
            ['user' => $user, 'paymentStatus' => 'paid'],
            ['createdAt' => 'DESC']
        );

        return $this->render('profile/index.html.twig', [
            'user' => $user,
            'form' => $form->createView(),
            'passwordForm' => $passwordForm->createView(),
            'donations' => $donations,
        ]);
    }
}
