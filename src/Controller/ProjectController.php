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
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\String\Slugger\SluggerInterface;

class ProjectController extends AbstractController
{
    #[Route('/campaigns', name: 'app_project_index')]
    public function index(Request $request, ProjectRepository $projectRepository, CommentRepository $commentRepository): Response
    {
        $search = $request->query->get('search', '');
        $page = max(1, $request->query->getInt('page', 1));
        $limit = 6;
        $offset = ($page - 1) * $limit;

        if ($search) {
            $qb = $projectRepository->createQueryBuilder('p')
                ->where('p.title LIKE :search OR p.description LIKE :search')
                ->setParameter('search', '%' . $search . '%')
                ->orderBy('p.createdAt', 'DESC');

            $totalProjects = count($qb->getQuery()->getResult());
            $projects = $qb->setFirstResult($offset)
                ->setMaxResults($limit)
                ->getQuery()
                ->getResult();
        } else {
            $totalProjects = $projectRepository->count([]);
            $projects = $projectRepository->findBy([], ['createdAt' => 'DESC'], $limit, $offset);
        }

        $totalPages = ceil($totalProjects / $limit);

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
            'currentPage' => $page,
            'totalPages' => $totalPages,
            'search' => $search,
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

    #[Route('/project/create', name: 'app_project_create')]
    public function create(Request $request, EntityManagerInterface $em, SluggerInterface $slugger): Response
    {
        if (!$this->getUser()) {
            return $this->redirectToRoute('app_login', ['login' => 'true']);
        }

        if ($request->isMethod('POST')) {
            $title = $request->request->get('title');
            $description = $request->request->get('description');
            $goalAmount = $request->request->get('goal_amount');
            $endDate = $request->request->get('end_date');
            $imageFile = $request->files->get('image');

            if (empty($title) || empty($description) || empty($goalAmount) || empty($endDate)) {
                $this->addFlash('error', 'All fields are required.');
                return $this->render('project/create.html.twig');
            }

            if ($goalAmount < 100) {
                $this->addFlash('error', 'Minimum goal amount is $100.');
                return $this->render('project/create.html.twig');
            }

            $project = new Project();
            $project->setTitle($title);
            $project->setDescription($description);
            $project->setGoalAmount($goalAmount);
            $project->setStartDate(new \DateTime());
            $project->setEndDate(new \DateTime($endDate));
            $project->setCreator($this->getUser());
            $project->setStatus('active');

            if ($imageFile) {
                $originalFilename = pathinfo($imageFile->getClientOriginalName(), PATHINFO_FILENAME);
                $safeFilename = $slugger->slug($originalFilename);
                $newFilename = $safeFilename.'-'.uniqid().'.'.$imageFile->guessExtension();

                try {
                    $imageFile->move(
                        $this->getParameter('kernel.project_dir').'/public/uploads/projects',
                        $newFilename
                    );
                    $project->setImage('/uploads/projects/'.$newFilename);
                } catch (FileException $e) {
                    $this->addFlash('error', 'Failed to upload image.');
                }
            }

            /** @var \App\Entity\User $user */
            $user = $this->getUser();
            if ($user->getRole() === 'ROLE_USER') {
                $user->setRole('ROLE_CREATOR');
            }

            $em->persist($project);
            $em->flush();

            $this->addFlash('success', 'Project created successfully!');
            return $this->redirectToRoute('app_dashboard_project', ['id' => $project->getId()]);
        }

        return $this->render('project/create.html.twig');
    }

    #[Route('/project/{id}', name: 'app_project_show')]
    public function show(Project $project): Response
    {
        $contributions = $project->getContributions()
            ->filter(function ($contribution) {
                return $contribution->getPaymentStatus() === 'paid';
            })
            ->toArray();

        usort($contributions, function ($a, $b) {
            return $b->getCreatedAt() <=> $a->getCreatedAt();
        });

        $userContributions = [];
        $anonIndex = 1;

        foreach ($contributions as $contribution) {
            $user = $contribution->getUser();

            $isAnonymous = method_exists($contribution, 'isIsAnonymous') ? $contribution->isIsAnonymous() : false;

            if ($isAnonymous || $user === null) {
                $userId = 'anon_' . $anonIndex++;

                $userContributions[$userId] = [
                    'user' => null,
                    'amount' => $contribution->getAmount(),
                    'isAnonymous' => true,
                    'createdAt' => $contribution->getCreatedAt(),
                ];
            } else {
                $userId = $user->getId();

                if (!isset($userContributions[$userId])) {
                    $userContributions[$userId] = [
                        'user' => $user,
                        'amount' => 0,
                        'isAnonymous' => false,
                        'createdAt' => $contribution->getCreatedAt(),
                    ];
                }

                $userContributions[$userId]['amount'] += $contribution->getAmount();
            }
        }

        usort($userContributions, function ($a, $b) {
            return $b['amount'] <=> $a['amount'];
        });

        $topContributions = array_slice($userContributions, 0, 3);
        $recentContributions = array_slice($contributions, 0, 5);

        return $this->render('project/show.html.twig', [
            'project' => $project,
            'comments' => $project->getComments(),
            'contributions' => $recentContributions,
            'topContributions' => $topContributions,
        ]);
    }
}
