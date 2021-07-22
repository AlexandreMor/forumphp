<?php

namespace App\Controller;

use App\Entity\Message;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use App\Repository\CategoryRepository;
use App\Repository\SubcategoryRepository;
use App\Repository\TopicRepository;
use App\Entity\Topic;
use App\Entity\User;
use App\Form\MessageType;
use App\Form\TopicType;
use App\Form\UserType;
use App\Repository\UserRepository;
use Symfony\Component\HttpFoundation\Request;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\ParamConverter;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;

/**
 * @Route("/", name="home_")
 */
class HomeController extends AbstractController
{
    /**
     * @Route("/", name="index")
     */
    public function index(): Response
    {
        return $this->render('home/index.html.twig');
    }

    /**
     * @Route("/forum", name="forum")
     */
    public function forum(CategoryRepository $catRepo, SubcategoryRepository $subcatRepo): Response
    {
        $cat = $catRepo->FindAll();
        $subcat = $subcatRepo->FindBy(['category' => $cat]);
        return $this->render('forum/index.html.twig', [
            'categories' => $cat,
            'subcat' => $subcat,
        ]);
    }

    /**
     * @IsGranted("ROLE_USER")
     * @Route("/profil", name="profil")
     */
    public function profil(Request $request): Response
    {
        $user = $this->getUser();
        $form = $this->createForm(UserType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {

            $this->getDoctrine()->getManager()->flush();
            $this->addFlash('success', 'Votre profil a bien été mis à jour');

            return $this->redirectToRoute('home_profil');
        }

        return $this->renderForm('user/profil.html.twig', [
            'user' => $user,
            'form' => $form,
        ]);
    }

    /**
     * @Route("/forum/{id}/ban", name="ban", methods={"POST"})
     */
    public function ban(int $id, UserRepository $userRepo)
    {
        if ($this->isGranted('ROLE_MOD')) {
            $user = $userRepo->findOneBy(['id' => $id]);
            $user= $user->setIsBanned(true);
            $this->addFlash('danger', "L'utilisateur a bien été banni");
            return $this->redirectToRoute('home_forum');
        }
        $this->addFlash('warning', 'Vous ne pouvez pas bannir cet utilisateur !');
            return $this->redirectToRoute('home_forum');
    }

    //Section topic, ajout, édition, vue

    /**
     * @Route("/forum/{name}", name="topics")
     */
    public function topics(string $name, TopicRepository $topicRepository, SubcategoryRepository $subcatRepo): Response
    {
        $title = $subcatRepo->findOneBy(['title' => $name]);
        return $this->render('topic/index.html.twig', [
            'topics' => $topicRepository->findBy(['subcategory' => $title]),
            'title' => $title
        ]);
    }

    /**
     * @IsGranted("ROLE_USER")
     * @Route("/forum/{name}/new", name="newtopic")
     */
    public function newTopic(string $name, Request $request, SubcategoryRepository $subcatRepo): Response
    {
        if ($this->getUser()->getIsBanned() == false) {
            $topic = new Topic();
            $subcat = $subcatRepo->findOneBy(['title' => $name]);
            $form = $this->createForm(TopicType::class, $topic);
            $form->handleRequest($request);

            if ($form->isSubmitted() && $form->isValid()) {
                $entityManager = $this->getDoctrine()->getManager();
                $entityManager->persist($topic);
                $topic->setAuthor($this->getUser());
                $topic->setSubcategory($subcat);
                $entityManager->flush();

                return $this->redirectToRoute('home_topic', ['name' => $name, 'id' => $topic->getId()]);
            }

            return $this->renderForm('topic/new.html.twig', [
                'topic' => $topic,
                'form' => $form,
                'sub' => $subcat
            ]);
        }
        $this->addFlash('danger', 'Vous êtes banni(e), vous ne pouvez plus interargir avec les autres utilisateurs.');
        return $this->redirectToRoute('home_index');
    }

    /**
     * @Route("/forum/{name}/{id}",defaults={"topicTitle"= false}, name="topic")
     */
    public function showTopic(string $name, int $id, TopicRepository $topicRepository, SubcategoryRepository $subcatRepo): Response
    {
        $subcatRepo->findOneBy(['title' => $name]);
        return $this->render('topic/show.html.twig', [
            'topic' => $topicRepository->findOneBy(['id' => $id])
        ]);
    }

    /**
     * @IsGranted("ROLE_USER")
     * @Route("/forum/{name}/{id}/edit", name="edittopic")
     */
    public function editTopic(string $name, int $id, Request $request, Topic $topic, SubcategoryRepository $subcatRepo, TopicRepository $topicRepository): Response
    {
        if ($this->getUser()->getIsBanned() == false) {
            $subcatRepo->findOneBy(['title' => $name]);
            $topic = $topicRepository->findOneBy(['id' => $id]);
            if ($topic->getAuthor() == $this->getUser() or $this->isGranted('ROLE_MOD')) {
                $form = $this->createForm(TopicType::class, $topic);
                $form->handleRequest($request);

                if ($form->isSubmitted() && $form->isValid()) {
                    $this->getDoctrine()->getManager()->flush();

                    return $this->redirectToRoute('home_topic', ['name' => $name, 'id' => $id]);
                }

                return $this->renderForm('topic/edit.html.twig', [
                    'topic' => $topic,
                    'form' => $form,
                ]);
            }
            return $this->redirectToRoute('home_topic', ['name' => $name, 'id' => $id]);
        }
        $this->addFlash('danger', 'Vous êtes banni(e), vous ne pouvez plus interargir avec les autres utilisateurs.');
        return $this->redirectToRoute('home_index');
    }

    //Section messages, édition, création

    /**
     * @Route("/forum/{name}/{id}/newMessage", name="newMessage", methods={"GET","POST"})
     */
    public function newMessage(string $name, int $id, Request $request, SubcategoryRepository $subcatRepo, TopicRepository $topicRepository): Response
    {
        if ($this->getUser()->getIsBanned() == false) {
            $subcatRepo->findOneBy(['title' => $name]);
            $topic = $topicRepository->findOneBy(['id' => $id]);
            $message = new Message();
            $form = $this->createForm(MessageType::class, $message);
            $form->handleRequest($request);

            if ($form->isSubmitted() && $form->isValid()) {
                $message->setTopic($topicRepository->findOneBy(['id' => $id]));
                $message->setAuthor($this->getUser());
                $entityManager = $this->getDoctrine()->getManager();
                $entityManager->persist($message);
                $entityManager->flush();

                return $this->redirectToRoute('home_topic', ['name' => $name, 'id' => $id]);
            }

            return $this->renderForm('message/new.html.twig', [
                'message' => $message,
                'form' => $form,
                'topic' => $topic
            ]);
        }
        $this->addFlash('danger', 'Vous êtes banni(e), vous ne pouvez plus interargir avec les autres utilisateurs.');
        return $this->redirectToRoute('home_index');
    }

    /**
     * @Route("/forum/{name}/{id}/editMessage", name="editMessage", methods={"GET","POST"})
     */
    public function editMessage(string $name, int $id, Request $request, Message $message, SubcategoryRepository $subcatRepo, TopicRepository $topicRepository): Response
    {
        if ($this->getUser()->getIsBanned() == false) {
            $subcatRepo->findOneBy(['title' => $name]);
            $topic = $topicRepository->findOneBy(['id' => $id]);
            if ($topic->getAuthor() == $this->getUser() or $this->isGranted('ROLE_MOD')) {
                $form = $this->createForm(MessageType::class, $message);
                $form->handleRequest($request);

                if ($form->isSubmitted() && $form->isValid()) {
                    $this->getDoctrine()->getManager()->flush();

                    return $this->redirectToRoute('home_topic', ['name' => $name, 'id' => $id]);
                }

                return $this->renderForm('message/edit.html.twig', [
                    'message' => $message,
                    'form' => $form,
                    'topic' => $topic
                ]);
            }
            return $this->redirectToRoute('home_topic', ['name' => $name, 'id' => $id]);
        }
        $this->addFlash('danger', 'Vous êtes banni(e), vous ne pouvez plus interargir avec les autres utilisateurs.');
        return $this->redirectToRoute('home_index');
    }
}
