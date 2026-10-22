<?php

namespace App\Controller;

use App\Entity\Album;
use App\Form\AlbumType;
use App\Form\AlbumSearchType;
use App\Repository\AlbumRepository;
use App\Service\MusicBrainzService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/album')]
final class AlbumController extends AbstractController
{
    #[Route('/', name: 'album_index', methods: ['GET'])]
    public function index(
        Request $request,
        AlbumRepository $albumRepository,
        MusicBrainzService $musicBrainzService
    ): Response {
        $form = $this->createForm(AlbumSearchType::class, null, [
            'method' => 'GET',
        ]);
        $form->handleRequest($request);

        $data = $form->getData() ?? [];

        $albums = $albumRepository->searchAlbums(
            $data['q'] ?? null,
            $data['genre'] ?? null,
            $data['yearFrom'] ?? null,
            $data['yearTo'] ?? null
        );

        $musicInfoMap = [];
        foreach ($albums as $album) {
            $musicInfoMap[$album->getId()] = $musicBrainzService->getAlbumContext(
                $album->getArtist(),
                $album->getTitle()
        );
    }

    return $this->render('album/index.html.twig', [
        'albums' => $albums,
        'search_form' => $form->createView(),
        'musicInfoMap' => $musicInfoMap,
    ]);
}

    /**
     * CREATE NEW ALBUM (ADMIN ONLY)
     */
    #[Route('/new', name: 'album_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        // ONLY ADMINS CAN CREATE ALBUMS
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $album = new Album();
        // set the creator to the currently logged in user
        $album->setCreatedBy($this->getUser());

        $form = $this->createForm(AlbumType::class, $album);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {

            /** @var UploadedFile|null $coverFile */
            $coverFile = $form->get('coverFile')->getData();

            if ($coverFile) {
                $uploadsDir = $this->getParameter('kernel.project_dir') . '/public/uploads/albums';

                if (!is_dir($uploadsDir)) {
                    mkdir($uploadsDir, 0777, true);
                }

                $newFilename = uniqid('cover_', true) . '.' . $coverFile->guessExtension();

                $coverFile->move($uploadsDir, $newFilename);

                $album->setCoverImagePath('uploads/albums/' . $newFilename);
            }

            $entityManager->persist($album);
            $entityManager->flush();

            return $this->redirectToRoute('album_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('album/new.html.twig', [
            'album' => $album,
            'form'  => $form,
        ]);
    }

    #[Route('/{id}', name: 'album_show', methods: ['GET'])]
    public function show(Album $album, MusicBrainzService $musicBrainzService): Response
    {
        $musicInfo = $musicBrainzService->getAlbumContext($album->getArtist(), $album->getTitle());

        return $this->render('album/show.html.twig', [
            'album' => $album,
            'musicInfo' => $musicInfo,
        ]);
    }

    #[Route('/{id}/edit', name: 'album_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Album $album, EntityManagerInterface $entityManager): Response
    {
        // ONLY ADMINS CAN EDIT ALBUMS
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $form = $this->createForm(AlbumType::class, $album);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {

            /** @var UploadedFile|null $coverFile */
            $coverFile = $form->get('coverFile')->getData();

            if ($coverFile) {
                $uploadsDir = $this->getParameter('kernel.project_dir') . '/public/uploads/albums';

                if (!is_dir($uploadsDir)) {
                    mkdir($uploadsDir, 0777, true);
                }

                $newFilename = uniqid('cover_', true) . '.' . $coverFile->guessExtension();

                $coverFile->move($uploadsDir, $newFilename);

                $album->setCoverImagePath('uploads/albums/' . $newFilename);
            }

            $album->setUpdatedAt(new \DateTime());

            $entityManager->flush();

            return $this->redirectToRoute('album_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('album/edit.html.twig', [
            'album' => $album,
            'form'  => $form,
        ]);
    }

    #[Route('/{id}', name: 'album_delete', methods: ['POST'])]
    public function delete(Request $request, Album $album, EntityManagerInterface $entityManager): Response
    {
        // ONLY ADMINS CAN DELETE ALBUMS
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        if ($this->isCsrfTokenValid('delete' . $album->getId(), $request->getPayload()->getString('_token'))) {
            $entityManager->remove($album);
            $entityManager->flush();
        }

        return $this->redirectToRoute('album_index', [], Response::HTTP_SEE_OTHER);
    }
}
