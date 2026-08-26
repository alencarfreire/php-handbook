# Código completo — 23.3 Symfony de porta de entrada

> Fonte que **roda**. Gerado por IA. Não existe no handbook original da CodeMate.

Walkthrough: [23.3](/23-projects/03-symfony) · [Baixar zip](/downloads/03-symfony.zip)

## Como rodar

```bash
unzip 03-symfony.zip
cd 03-symfony
composer install
php bin/console doctrine:schema:update --force
php -S localhost:8002 -t public
```

Abre http://localhost:8002. `vendor/` não vai no zip — `composer install` baixa.

## `README.md`

````markdown
# 03 — Symfony de porta de entrada

CRUD de tasks. Symfony 7.4, Twig, Doctrine, SQLite.

**Gerado por IA.** Não faz parte do handbook original da CodeMate.

## O que você treina

- `#[Route]` no controller
- Autowire (repository e EntityManager caem no método)
- Form + CSRF
- Twig (`path`, `form_*`)
- Doctrine entity / persist / flush

## Como rodar

```bash
composer install
php bin/console doctrine:database:create --if-not-exists
php bin/console doctrine:schema:update --force
php -S localhost:8002 -t public
```

Abre http://localhost:8002

## O que não entra (de propósito)

- Auth / Security
- API JSON
- Webpack / Asset Mapper
- Testes
````

## `config/packages/csrf.yaml`

```yaml
# CSRF clássico (campo hidden + sessão). O recipe vem com stateless;
# neste bolso o token no form é o que você explica na entrevista.
framework:
    csrf_protection: ~
    form:
        csrf_protection:
            enabled: true
```

## `src/Entity/Task.php`

```php
<?php

namespace App\Entity;

use App\Repository\TaskRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

// Entidade Doctrine. A tabela nasce no schema:update — sem SQL na mão.
#[ORM\Entity(repositoryClass: TaskRepository::class)]
class Task
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 180)]
    #[Assert\NotBlank(message: 'Título obrigatório.')]
    #[Assert\Length(max: 180)]
    private string $title = '';

    #[ORM\Column]
    private bool $done = false;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): void
    {
        $this->title = $title;
    }

    public function isDone(): bool
    {
        return $this->done;
    }

    public function setDone(bool $done): void
    {
        $this->done = $done;
    }

    public function toggle(): void
    {
        $this->done = !$this->done;
    }
}
```

## `src/Repository/TaskRepository.php`

```php
<?php

namespace App\Repository;

use App\Entity\Task;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

// Repository do Doctrine. find/persist vêm da classe pai.
/** @extends ServiceEntityRepository<Task> */
class TaskRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Task::class);
    }

    /** @return list<Task> */
    public function allNewestFirst(): array
    {
        return $this->createQueryBuilder('t')
            ->orderBy('t.id', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
```

## `src/Form/TaskType.php`

```php
<?php

namespace App\Form;

use App\Entity\Task;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

// Form amarrado na entidade. CSRF o Symfony coloca sozinho.
class TaskType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('title', TextType::class, [
            'label' => 'Título',
            'attr'  => ['placeholder' => 'Comprar ração'],
        ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Task::class,
        ]);
    }
}
```

## `src/Controller/TaskController.php`

```php
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
```

## `templates/base.html.twig`

```twig
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{% block title %}Tasks Symfony{% endblock %}</title>
    <style>
        body { font-family: sans-serif; max-width: 36rem; margin: 2.5rem auto; padding: 0 1rem; }
        form.nova { display: flex; gap: 0.5rem; margin: 1rem 0 1.5rem; }
        form.nova input { flex: 1; padding: 0.4rem; }
        li { display: flex; align-items: center; gap: 0.5rem; margin: 0.4rem 0; }
        li.done span { text-decoration: line-through; color: #666; }
        button { padding: 0.35rem 0.7rem; }
        .hint { color: #555; font-size: 0.9rem; }
    </style>
</head>
<body>
    {% block body %}{% endblock %}
</body>
</html>
```

## `templates/task/index.html.twig`

```twig
{% extends 'base.html.twig' %}

{% block body %}
    <h1>Tasks</h1>
    <p class="hint">Symfony 7 + Twig + Doctrine + SQLite. Sem auth neste bolso.</p>

    {{ form_start(form, { attr: { class: 'nova' } }) }}
        {{ form_widget(form.title) }}
        <button type="submit">Criar</button>
    {{ form_end(form) }}

    {% if tasks is empty %}
        <p>Nenhuma task. Cria a primeira.</p>
    {% else %}
        <ul>
            {% for task in tasks %}
                <li class="{{ task.done ? 'done' : '' }}">
                    <span>{{ task.title }}</span>
                    <form method="post" action="{{ path('task_toggle', { id: task.id }) }}">
                        <input type="hidden" name="_token" value="{{ csrf_token('task' ~ task.id) }}">
                        <button type="submit">{{ task.done ? 'Reabrir' : 'Fechar' }}</button>
                    </form>
                    <form method="post" action="{{ path('task_delete', { id: task.id }) }}">
                        <input type="hidden" name="_token" value="{{ csrf_token('task' ~ task.id) }}">
                        <button type="submit">Apagar</button>
                    </form>
                </li>
            {% endfor %}
        </ul>
    {% endif %}
{% endblock %}
```

*Parte do [PHP/Laravel Interview Handbook](/) — seção gerada por IA, só neste fork.*
