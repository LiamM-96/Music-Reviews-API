<?php

namespace App\Controller;

use App\Entity\Album;
use App\Entity\Review;
use App\Form\ReviewType;
use App\Repository\ReviewRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/review')]
class ReviewController extends AbstractController
{
    #[Route('/new/{albumId}', name: 'review_new', methods: ['GET', 'POST'])]
    public function new(int $albumId, Request $request, EntityManagerInterface $em): Response
    {
        $this->denyAccessUnlessGranted('ROLE_USER');

        $album = $em->getRepository(Album::class)->find($albumId);

        if (!$album) {
            throw $this->createNotFoundException('Album not found.');
        }

        $review = new Review();
        $review->setAlbum($album);
        $review->setUser($this->getUser());

        $form = $this->createForm(ReviewType::class, $review);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($review);
            $em->flush();

            return $this->redirectToRoute('album_show', ['id' => $albumId]);
        }

        return $this->render('review/new.html.twig', [
            'review' => $review,
            'form' => $form,
            'album' => $album,
        ]);
    }

    #[Route('/{id}/edit', name: 'review_edit', methods: ['GET', 'POST'])]
    public function edit(Review $review, Request $request, EntityManagerInterface $em): Response
    {
        if ($review->getUser() !== $this->getUser() && !$this->isGranted('ROLE_ADMIN')) {
            throw $this->createAccessDeniedException();
        }

        $form = $this->createForm(ReviewType::class, $review);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {

            $review->setUpdatedAt(new \DateTime());

            $em->flush();

            return $this->redirectToRoute('album_show', [
                'id' => $review->getAlbum()->getId()
            ]);
        }

        return $this->render('review/edit.html.twig', [
            'review' => $review,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'review_delete', methods: ['POST'])]
    public function delete(Review $review, Request $request, EntityManagerInterface $em): Response
    {
        if ($review->getUser() !== $this->getUser() && !$this->isGranted('ROLE_ADMIN')) {
            throw $this->createAccessDeniedException();
        }

        if ($this->isCsrfTokenValid('delete'.$review->getId(), $request->request->get('_token'))) {
            $albumId = $review->getAlbum()->getId();
            $em->remove($review);
            $em->flush();
            return $this->redirectToRoute('album_show', ['id' => $albumId]);
        }

        return $this->redirectToRoute('album_index');
    }
}
