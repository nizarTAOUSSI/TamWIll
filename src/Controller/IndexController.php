<?php

namespace App\Controller;

use App\Repository\ProjectRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class IndexController extends AbstractController
{
    #[Route('/', name: 'TamWill')]
    public function index(ProjectRepository $projectRepository): Response
    {
        $projects = $projectRepository->findBy(
            [],
            ['createdAt' => 'DESC'],
            6
        );
        return $this->render('index/index.html.twig', [
            'controller_name' => 'IndexController',
            'progress' => '50',
            'goal' => '900,000,500',
            'projects' => $projects,
        ]);
    }
}
