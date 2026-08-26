<?php

namespace App\Controller;

use App\Entity\Task;
use App\Form\TaskType;
use App\Repository\TaskRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Attribute\Route;

// Rotas no atributo. Sem routes.yaml extra. Autowire injeta o repository.
final class TaskController extends AbstractController
{
    #[Route('/', name: 'task_index', methods: ['GET', 'POST'])]
    public function index(
        Request $request,
        TaskRepository $tasks,
        EntityManagerInterface $em,
    ): Response {
        $task = new Task();
        $form = $this->createForm(TaskType::class, $task);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($task);
            $em->flush();

            return $this->redirectToRoute('task_index');
        }

        return $this->render('task/index.html.twig', [
            'tasks' => $tasks->allNewestFirst(),
            'form'  => $form,
        ]);
    }

    #[Route('/{id}/toggle', name: 'task_toggle', methods: ['POST'])]
    public function toggle(Request $request, Task $task, EntityManagerInterface $em): Response
    {
        // Param converter: {id} vira Task. 404 se não achar.
        $this->assertCsrf($request, $task);
        $task->toggle();
        $em->flush();

        return $this->redirectToRoute('task_index');
    }

    #[Route('/{id}/delete', name: 'task_delete', methods: ['POST'])]
    public function delete(Request $request, Task $task, EntityManagerInterface $em): Response
    {
        $this->assertCsrf($request, $task);
        $em->remove($task);
        $em->flush();

        return $this->redirectToRoute('task_index');
    }

    private function assertCsrf(Request $request, Task $task): void
    {
        if (!$this->isCsrfTokenValid('task' . $task->getId(), (string) $request->request->get('_token'))) {
            throw new BadRequestHttpException('CSRF inválido.');
        }
    }
}
