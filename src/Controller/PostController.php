<?php

namespace App\Controller;

use App\Entity\Post;
use App\Entity\User;
use App\Entity\Likes;
use App\Form\PostType;
use App\Entity\Comment;
use Doctrine\ORM\EntityManagerInterface;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\String\Slugger\SluggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\Exception\FileException;

class PostController extends AbstractController
{
    private $em;

    public function __construct(EntityManagerInterface $em)
    {
        $this->em = $em;
    }

    /**
     * @Route("/post/{id}", name="postDetails")
     */
    public function postDetails(Post $post)
    {
        return $this->render('post/post-details.html.twig', ['post' => $post]);
    }

    /**
     * @Route("/comment/{id}", name="comment_new")
     */
    public function postComment(Request $request, Post $post)
    {
        $datos = $request->request->all();
        $comment = $datos['content'] ?? null;
        $newcomment = new Comment();
        $newcomment->setContent($comment);
        $newcomment->setPublishedAt($post->getCreationDate());
        $newcomment->setPost($post);

        /** @var User */
        $user = $this->getUser();

        if ($user) {
            $newcomment->setauthorName($user->getEmail());
        }

        $this->em->persist($newcomment);
        $this->em->flush();

        return $this->render('post/post-details.html.twig', ['post' => $post]);
    }

    /**
     * @Route("/like/{id}", name="post_like")
     */
    public function postLike(Post $post)
    {
        $user = $this->getUser();

        if (!$this->getUser()) {
            return $this->redirectToRoute('login');
        }

        $like = $this->em->getRepository(Likes::class)->findOneBy(['user' => $user, 'post' => $post]);

        if ($like) {
            $this->em->remove($like);
        } else {
            $like = new Likes();
            $user = $this->getUser();
            $like->setUser($user);
            $like->setPost($post);
            $this->em->persist($like);
        }

        $this->em->flush();
        return new JsonResponse(["iLikeIt" => $post->getILikeIt(), "amount" => count($post->getLikes()->toArray())]);
    }

    /**
     * @Route("/", name="app_post")
     */
    public function index(Request $request, SluggerInterface $slugger, PaginatorInterface $paginator): Response
    {
        $post = new Post();
        $query = $this->em->getRepository(Post::class)->findAll();

        $pagination = $paginator->paginate(
            $query, /* query NOT result */
            $request->query->getInt('page', 1), /*page number*/
            2 /*limit per page*/
        );

        $form = $this->createForm(PostType::class, $post);
        $form->handleRequest($request);

        if (!$this->getUser()) {
            $this->addFlash('error', "no estas logueado.");

            return $this->render('post/index.html.twig', [
                'controller_name' => 'fruta',
                'form' => $form->createView(),
                'posts' => null
            ]);
        }

        if ($form->isSubmitted() && $form->isValid()) {
            $file = $form->get('file')->getData();
            $url = str_replace(" ", "-", $form->get('title')->getData());

            if ($file) {
                $originalFilename = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
                $safeFilename = $slugger->slug($originalFilename);
                $newFilename = $safeFilename . '-' . uniqid() . '.' . $file->guessExtension();
                try {
                    $file->move(
                        $this->getParameter('files_directory'),
                        $newFilename
                    );
                } catch (FileException $e) {
                    throw new \Exception('Ups! there is a problem with your file');
                }
                $post->setFile($newFilename);
            }

            $post->setUrl($url);
            $user = $this->getUser();
            $post->setUser($user);


            return $this->redirectToRoute('app_post');
        }

        return $this->render('post/index.html.twig', [
            'controller_name' => 'fruta',
            'form' => $form->createView(),
            'posts' => $pagination
        ]);
    }
}
