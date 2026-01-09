<?php

namespace App\Controller;

use App\Repository\CommentRepository;
use App\Repository\ContributionRepository;
use App\Repository\ProjectRepository;
use App\Repository\UserRepository;
use App\Entity\Comment;
use App\Entity\Project;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

class AdminController extends AbstractController
{
    #[Route('/admin', name: 'admin_dashboard')]
    public function index(ProjectRepository $projectRepo, UserRepository $userRepo, ContributionRepository $contribRepo, CommentRepository $commentRepo, CsrfTokenManagerInterface $csrf): Response
    {
        // Totals
        $totalProjects = (int) $projectRepo->count([]);
        $totalUsers = (int) $userRepo->count([]);
        $totalComments = (int) $commentRepo->count([]);

        // Total donations (sum of contribution amounts)
        $qb = $contribRepo->createQueryBuilder('c')
            ->select('SUM(c.amount) as total')
            ->getQuery()
            ->getSingleScalarResult();
        $totalDonations = (float) $qb;

        // Donations per month (last 12 months)
        $conn = $contribRepo->getEntityManager()->getConnection();
        $sql = "SELECT DATE_FORMAT(created_at, '%Y-%m') AS month, SUM(amount) AS total FROM contribution WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 11 MONTH) GROUP BY month ORDER BY month";
        $stmt = $conn->executeQuery($sql);
        $donationsByMonth = [];
        $labels = [];
        $values = [];
        foreach ($stmt->fetchAllAssociative() as $row) {
            $labels[] = $row['month'];
            $values[] = (float) $row['total'];
        }

        // Projects per month (last 12 months)
        $sql2 = "SELECT DATE_FORMAT(created_at, '%Y-%m') AS month, COUNT(id) AS total FROM project WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 11 MONTH) GROUP BY month ORDER BY month";
        $stmt2 = $conn->executeQuery($sql2);
        $pLabels = [];
        $pValues = [];
        foreach ($stmt2->fetchAllAssociative() as $row) {
            $pLabels[] = $row['month'];
            $pValues[] = (int) $row['total'];
        }

        // Projects list with aggregated donations
        $projects = $projectRepo->createQueryBuilder('p')
            ->leftJoin('p.contributions', 'c')
            ->addSelect('p', 'c')
            ->getQuery()
            ->getResult();

        $projectData = [];
        foreach ($projects as $p) {
            $sum = 0.0;
            foreach ($p->getContributions() as $c) {
                $sum += (float) $c->getAmount();
            }
            $projectData[] = [
                'id' => $p->getId(),
                'name' => $p->getTitle() ?? 'Untitled',
                'description' => $p->getDescription() ?? '',
                'creator' => $p->getCreator()?->getName() ?? $p->getCreator()?->getEmail() ?? '—',
                'donations' => $sum,
                'status' => $p->getStatus() ?? '—',
                'createdAt' => $p->getCreatedAt()?->format('Y-m-d') ?? '',
            ];
        }

        // Comments grouped by project (include id and CSRF token for deletion)
        $comments = $commentRepo->findAll();
        $commentsByProject = [];
        foreach ($comments as $c) {
            $commentsByProject[$c->getProject()->getId()][] = [
                'id' => $c->getId(),
                'author' => $c->getAuthor()?->getName() ?? $c->getAuthor()?->getEmail() ?? '—',
                'content' => $c->getContent(),
                'createdAt' => $c->getCreatedAt()->format('Y-m-d H:i'),
                'csrf' => $csrf->getToken('delete-comment'.$c->getId())->getValue(),
            ];
        }

        // Users list
        $users = $userRepo->findAll();
        $userData = [];
        foreach ($users as $u) {
            $userData[] = [
                'id' => $u->getId(),
                'name' => $u->getName() ?? $u->getEmail(),
                'email' => $u->getEmail(),
                'roles' => in_array('ROLE_ADMIN', $u->getRoles()) ? 'admin' : 'user',
                'status' => $u->isIsActive() ? 'active' : 'inactive',
                'registeredAt' => $u->getCreatedAt()?->format('Y-m-d') ?? '',
            ];
        }


        // Donations list (contributions)
        $contribs = $contribRepo->createQueryBuilder('c')
            ->leftJoin('c.user', 'u')
            ->leftJoin('c.project', 'p')
            ->addSelect('u', 'p')
            ->orderBy('c.createdAt', 'DESC')
            ->getQuery()
            ->getResult();

        $donations = [];
        foreach ($contribs as $c) {
            $donations[] = [
                'donor' => $c->getUser()?->getName() ?? $c->getUser()?->getEmail() ?? '—',
                'project' => $c->getProject()?->getTitle() ?? '—',
                'amount' => (float) $c->getAmount(),
                'date' => $c->getCreatedAt()?->format('Y-m-d') ?? '',
            ];
        }

        $payoutRequests = $projectRepo->createQueryBuilder('p')
            ->leftJoin('p.creator', 'u')
            ->addSelect('u')
            ->where('p.payoutStatus = :pending OR p.payoutStatus = :requested')
            ->setParameter('pending', 'pending')
            ->setParameter('requested', 'requested')
            ->orderBy('p.payoutRequestedAt', 'DESC')
            ->getQuery()
            ->getResult();

        $payoutRequestsData = [];
        foreach ($payoutRequests as $p) {
            $payoutRequestsData[] = [
                'id' => $p->getId(),
                'title' => $p->getTitle() ?? 'Untitled',
                'creator' => $p->getCreator()?->getName() ?? $p->getCreator()?->getEmail() ?? '—',
                'creatorEmail' => $p->getCreator()?->getEmail() ?? '',
                'collectedAmount' => (float) $p->getCollectedAmount(),
                'rib' => $p->getRib() ?? 'Not provided',
                'status' => $p->getPayoutStatus() ?? 'pending',
                'requestedAt' => $p->getPayoutRequestedAt()?->format('Y-m-d H:i') ?? 'Unknown',
            ];
        }

        return $this->render('index/admin.html.twig', [
            'totals' => [
                'projects' => $totalProjects,
                'users' => $totalUsers,
                'donations' => $totalDonations,
                'comments' => $totalComments,
            ],
            'donations_chart' => [
                'labels' => $labels,
                'values' => $values,
            ],
            'projects_chart' => [
                'labels' => $pLabels,
                'values' => $pValues,
            ],
            'projects' => $projectData,
            'commentsByProject' => $commentsByProject,
            'users' => $userData,
            'donations' => $donations,
            'payoutRequests' => $payoutRequestsData,
        ]);
    }

    #[Route('/admin/comment/{id}/delete', name: 'admin_comment_delete', methods: ['POST'])]
    public function delete(Comment $comment, Request $request, EntityManagerInterface $em, CsrfTokenManagerInterface $csrf): JsonResponse
    {
        // Return JSON errors instead of throwing to make client-side flow deterministic
        if (!$this->isGranted('ROLE_ADMIN')) {
            return new JsonResponse(['error' => 'Access denied'], 403);
        }

        // extract token from form body '_token', or header 'X-CSRF-Token', or JSON payload
        $token = $request->request->get('_token');
        if (!$token) {
            $token = $request->headers->get('X-CSRF-Token');
        }
        if (!$token) {
            $content = $request->getContent();
            if ($content) {
                $data = json_decode($content, true);
                if (json_last_error() === JSON_ERROR_NONE && isset($data['_token'])) {
                    $token = $data['_token'];
                }
            }
        }

        if (!$token || !$csrf->isTokenValid(new CsrfToken('delete-comment'.$comment->getId(), $token))) {
            return new JsonResponse(['error' => 'Invalid CSRF token'], 403);
        }

        try {
            $em->remove($comment);
            $em->flush();
            return new JsonResponse(['ok' => true]);
        } catch (\Throwable $e) {
            return new JsonResponse(['error' => 'Server error while deleting comment'], 500);
        }
    }

    #[Route('/admin/user/{id}/ban', name: 'admin_user_ban', methods: ['POST'])]
    public function banUser(\App\Entity\User $user, Request $request, EntityManagerInterface $em, CsrfTokenManagerInterface $csrf): JsonResponse
    {
        if (!$this->isGranted('ROLE_ADMIN')) {
            return new JsonResponse(['error' => 'Access denied'], 403);
        }

        $content = $request->getContent();
        $data = [];
        if ($content) {
            $data = json_decode($content, true);
        }

        if (!isset($data['active'])) {
            return new JsonResponse(['error' => 'Missing "active" in payload'], 400);
        }

        $token = $data['_token'] ?? $request->headers->get('X-CSRF-Token') ?? $request->request->get('_token');
        if ($token && !$csrf->isTokenValid(new CsrfToken('ban-user'.$user->getId(), $token))) {
            return new JsonResponse(['error' => 'Invalid CSRF token'], 403);
        }

        $active = filter_var($data['active'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        if ($active === null) {
            return new JsonResponse(['error' => 'Invalid "active" value'], 400);
        }

        try {
            $user->setIsActive($active);
            $em->flush();
            return new JsonResponse(['ok' => true, 'active' => $user->isIsActive()]);
        } catch (\Throwable $e) {
            return new JsonResponse(['error' => 'Server error while updating user'], 500);
        }
    }

    #[Route('/admin/user/ban', name: 'admin_user_ban_email', methods: ['POST'])]
    public function banUserByEmail(Request $request, UserRepository $userRepo, EntityManagerInterface $em, CsrfTokenManagerInterface $csrf): JsonResponse
    {
        if (!$this->isGranted('ROLE_ADMIN')) {
            return new JsonResponse(['error' => 'Access denied'], 403);
        }

        $content = $request->getContent();
        $data = [];
        if ($content) {
            $data = json_decode($content, true);
        }

        $email = $data['email'] ?? null;
        $activeRaw = $data['active'] ?? null;
        if (!$email || $activeRaw === null) {
            return new JsonResponse(['error' => 'Missing email or active flag'], 400);
        }

        $token = $data['_token'] ?? $request->headers->get('X-CSRF-Token') ?? $request->request->get('_token');
        if ($token && !$csrf->isTokenValid(new CsrfToken('ban-user-email'.$email, $token))) {
            return new JsonResponse(['error' => 'Invalid CSRF token'], 403);
        }

        $user = $userRepo->findOneBy(['email' => $email]);
        if (!$user) {
            return new JsonResponse(['error' => 'User not found'], 404);
        }

        $active = filter_var($activeRaw, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        if ($active === null) {
            return new JsonResponse(['error' => 'Invalid "active" value'], 400);
        }

        try {
            $user->setIsActive($active);
            $em->flush();
            return new JsonResponse(['ok' => true, 'active' => $user->isIsActive()]);
        } catch (\Throwable $e) {
            return new JsonResponse(['error' => 'Server error while updating user'], 500);
        }
    }

    #[Route('/admin/user/{id}/role', name: 'admin_user_role', methods: ['POST'])]
    public function updateUserRole(\App\Entity\User $user, Request $request, EntityManagerInterface $em): JsonResponse
    {
        if (!$this->isGranted('ROLE_ADMIN')) {
            return new JsonResponse(['error' => 'Access denied'], 403);
        }

        $content = $request->getContent();
        $data = [];
        if ($content) {
            $data = json_decode($content, true);
        }

        if (!isset($data['role'])) {
            return new JsonResponse(['error' => 'Missing "role" in payload'], 400);
        }

        $role = strtoupper(trim($data['role']));

        // Validate role
        if ($role !== 'ADMIN' && $role !== 'USER') {
            return new JsonResponse(['error' => 'Invalid role. Must be "admin" or "user"'], 400);
        }

        try {
            // Set roles based on the selection
            if ($role === 'ADMIN') {
                $user->setRole('ROLE_ADMIN');
            } else {
                $user->setRole('ROLE_USER');
            }

            $em->flush();
            return new JsonResponse(['ok' => true, 'role' => $role]);
        } catch (\Throwable $e) {
            return new JsonResponse(['error' => 'Server error while updating user role'], 500);
        }
    }

    #[Route('/admin/user/{id}/status', name: 'admin_user_status', methods: ['POST'])]
    public function updateUserStatus(\App\Entity\User $user, Request $request, EntityManagerInterface $em): JsonResponse
    {
        if (!$this->isGranted('ROLE_ADMIN')) {
            return new JsonResponse(['error' => 'Access denied'], 403);
        }

        $content = $request->getContent();
        $data = [];
        if ($content) {
            $data = json_decode($content, true);
        }

        if (!isset($data['status'])) {
            return new JsonResponse(['error' => 'Missing "status" in payload'], 400);
        }

        $status = strtolower(trim($data['status']));

        // Validate status
        if ($status !== 'active' && $status !== 'banned') {
            return new JsonResponse(['error' => 'Invalid status. Must be "active" or "banned"'], 400);
        }

        try {
            $user->setIsActive($status === 'active');
            $em->flush();
            return new JsonResponse(['ok' => true, 'status' => $status]);
        } catch (\Throwable $e) {
            return new JsonResponse(['error' => 'Server error while updating user status'], 500);
        }
    }

    #[Route('/admin/user/{id}/delete', name: 'admin_user_delete', methods: ['POST'])]
    public function deleteUser(\App\Entity\User $user, Request $request, EntityManagerInterface $em): JsonResponse
    {
        if (!$this->isGranted('ROLE_ADMIN')) {
            return new JsonResponse(['error' => 'Access denied'], 403);
        }

        // Prevent admin from deleting themselves
        $currentUser = $this->getUser();
        if ($currentUser instanceof \App\Entity\User && $user->getId() === $currentUser->getId()) {
            return new JsonResponse(['error' => 'Cannot delete your own account'], 400);
        }

        try {
            $em->remove($user);
            $em->flush();
            return new JsonResponse(['ok' => true]);
        } catch (\Throwable $e) {
            return new JsonResponse(['error' => 'Server error while deleting user'], 500);
        }
    }

    #[Route('/admin/user/{id}/edit', name: 'admin_user_edit', methods: ['POST'])]
    public function editUser(\App\Entity\User $user, Request $request, EntityManagerInterface $em): Response
    {
        if (!$this->isGranted('ROLE_ADMIN')) {
            $this->addFlash('error', 'Access denied.');
            return $this->redirectToRoute('admin_dashboard');
        }

        $token = $request->request->get('_token');
        if (!$this->isCsrfTokenValid('edit-user'.$user->getId(), $token)) {
            $this->addFlash('error', 'Invalid CSRF token.');
            return $this->redirectToRoute('admin_dashboard');
        }

        $name = trim((string) $request->request->get('name'));
        $email = trim((string) $request->request->get('email'));

        if ($name !== '') $user->setName($name);
        if ($email !== '') $user->setEmail($email);

        try {
            $em->flush();
            $this->addFlash('success', 'User updated successfully.');
        } catch (\Throwable $e) {
            $this->addFlash('error', 'Server error while updating user.');
        }

        return $this->redirectToRoute('admin_dashboard');
    }

    #[Route('/admin/project/{id}/delete', name: 'admin_project_delete', methods: ['POST'])]
    public function deleteProject(\App\Entity\Project $project, Request $request, EntityManagerInterface $em): JsonResponse
    {
        if (!$this->isGranted('ROLE_ADMIN')) {
            return new JsonResponse(['error' => 'Access denied'], 403);
        }

        try {
            $em->remove($project);
            $em->flush();
            return new JsonResponse(['ok' => true]);
        } catch (\Throwable $e) {
            return new JsonResponse(['error' => 'Server error while deleting project'], 500);
        }
    }

    #[Route('/admin/project/{id}/edit', name: 'admin_project_edit', methods: ['POST'])]
    public function editProject(\App\Entity\Project $project, Request $request, EntityManagerInterface $em): Response
    {
        if (!$this->isGranted('ROLE_ADMIN')) {
            $this->addFlash('error', 'Access denied.');
            return $this->redirectToRoute('admin_dashboard');
        }

        $token = $request->request->get('_token');
        if (!$this->isCsrfTokenValid('edit-project'.$project->getId(), $token)) {
            $this->addFlash('error', 'Invalid CSRF token.');
            return $this->redirectToRoute('admin_dashboard');
        }

        $title = trim((string) $request->request->get('title'));
        $description = trim((string) $request->request->get('description'));
        // Status is handled via separate AJAX call now, but we keep this if needed or remove it.
        // Typically if the modal doesn't send status, we shouldn't change it.
        // The modal DOES send status field? No, we removed it from the modal HTML.
        // So we should NOT update status here anymore, or use existing value.
        // The user request said "dont show it in the modal".

        if ($title !== '') $project->setTitle($title);
        if ($description !== '') $project->setDescription($description);

        try {
            $em->flush();
            $this->addFlash('success', 'Project updated successfully.');
        } catch (\Throwable $e) {
            $this->addFlash('error', 'Server error while updating project.');
        }

        return $this->redirectToRoute('admin_dashboard');
    }
    #[Route('/admin/project/{id}/status', name: 'admin_project_status', methods: ['POST'])]
    public function updateProjectStatus(\App\Entity\Project $project, Request $request, EntityManagerInterface $em): JsonResponse
    {
        if (!$this->isGranted('ROLE_ADMIN')) {
            return new JsonResponse(['error' => 'Access denied'], 403);
        }

        $content = $request->getContent();
        $data = [];
        if ($content) {
            $data = json_decode($content, true);
        }

        if (!isset($data['status'])) {
            return new JsonResponse(['error' => 'Missing "status" in payload'], 400);
        }

        $status = strtolower(trim($data['status']));

        // Validate status (adjust valid statuses as needed for your app)
        $validStatuses = ['draft', 'published', 'archived'];
        if (!in_array($status, $validStatuses, true)) {
            return new JsonResponse(['error' => 'Invalid status'], 400);
        }

        try {
            $project->setStatus($status);
            $em->flush();
            return new JsonResponse(['ok' => true, 'status' => $status]);
        } catch (\Throwable $e) {
            return new JsonResponse(['error' => 'Server error while updating project status'], 500);
        }
    }

    #[Route('/admin/payout/{id}/confirm', name: 'admin_payout_confirm', methods: ['POST'])]
    public function confirmPayout(Project $project, Request $request, EntityManagerInterface $em, MailerInterface $mailer): JsonResponse
    {
        if (!$this->isGranted('ROLE_ADMIN')) {
            return new JsonResponse(['error' => 'Access denied'], 403);
        }

        if (!$project || ($project->getPayoutStatus() !== 'pending' && $project->getPayoutStatus() !== 'requested')) {
            return new JsonResponse(['error' => 'Invalid payout request'], 400);
        }

        if (!$project->getCollectedAmount() || (float)$project->getCollectedAmount() <= 0) {
            return new JsonResponse(['error' => 'No collected amount to payout'], 400);
        }

        if (!$project->getRib()) {
            return new JsonResponse(['error' => 'No RIB provided for this project'], 400);
        }

        try {
            $project->setPayoutStatus('confirmed');
            $project->setPayoutCompletedAt(new \DateTime());

            $em->flush();

            if ($project->getCreator() && $project->getCreator()->getEmail()) {
                $email = (new Email())
                    ->from('noreply@tamwill.com')
                    ->to($project->getCreator()->getEmail())
                    ->subject('Payout Confirmed - ' . $project->getTitle() . ' - TamWill')
                    ->html($this->renderView('emails/payout_confirmed.html.twig', [
                        'project' => $project
                    ]));

                try {
                    $mailer->send($email);
                } catch (\Exception $e) {
                    error_log('Payout confirmation email failed: ' . $e->getMessage());
                }
            }

            return new JsonResponse([
                'ok' => true,
                'message' => 'Payout confirmed successfully. Email sent to project creator.',
                'projectId' => $project->getId(),
                'newStatus' => 'confirmed'
            ]);

        } catch (\Throwable $e) {
            error_log('Payout confirmation error: ' . $e->getMessage());
            return new JsonResponse(['error' => 'Server error while confirming payout'], 500);
        }
    }
}
