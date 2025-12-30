<?php

namespace App\Controller;

use App\Repository\ProjectRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use App\Entity\Comment;
use App\Entity\Project;
use App\Repository\CommentRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;

class ProjectController extends AbstractController
{
    #[Route('/campaigns', name: 'app_project_index')]
    public function index(ProjectRepository $projectRepository, CommentRepository $commentRepository): Response
    {
        $projects = $projectRepository->findBy([]);
        $comments = $commentRepository->findBy([]);
        $projectComments = [];
        foreach ($projects as $project) {
            $projectComments[$project->getId()] = [];
        }
        foreach ($comments as $comment) {
            $pid = $comment->getProject()->getId();
            if (isset($projectComments[$pid])) {
                $projectComments[$pid][] = $comment;
            }
        }
        return $this->render('project/index.html.twig', [
            'projects' => $projects,
            'projectComments' => $projectComments,
        ]);
    }

    #[Route('/campaigns/{id}/comment', name: 'app_project_comment', methods: ['POST'])]
    public function addComment(
        int $id,
        Request $request,
        ProjectRepository $projectRepository,
        EntityManagerInterface $em,
    ): Response {
        $project = $projectRepository->find($id);
        if (!$project) {
            throw $this->createNotFoundException('Project not found');
        }
        $content = $request->request->get('content');
        if (!$content || !$this->getUser()) {
            return $this->redirectToRoute('app_project_index');
        }
        $comment = new Comment();
        $comment->setContent($content);
        $comment->setAuthor($this->getUser());
        $comment->setProject($project);
        $em->persist($comment);
        $em->flush();

        $referer = $request->headers->get('referer');
        if ($referer) {
            return $this->redirect($referer);
        }
        return $this->redirectToRoute('app_project_index');
    }

    #[Route('/project/{id}', name: 'app_project_show')]
    public function show(Project $project): Response
    {
        $comments = $project->getComments();
        return $this->render('project/show.html.twig', [
            'project' => $project,
            'comments' => $comments,
        ]);
    }
}
