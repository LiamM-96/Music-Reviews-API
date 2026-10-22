<?php

namespace App\Controller\Api;

use App\Entity\Album;
use App\Entity\Review;
use App\Repository\ReviewRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/reviews', name: 'api_reviews_')]
class ReviewApiController extends AbstractController
{
    #[Route('', name: 'index', methods: ['GET'])]
    public function index(ReviewRepository $reviewRepository): JsonResponse
    {
        $reviews = $reviewRepository->findBy([], ['createdAt' => 'DESC']);

        return $this->json([
            'data' => array_map(fn (Review $review) => $this->serializeReview($review), $reviews),
            'count' => count($reviews),
        ], Response::HTTP_OK);
    }

    #[Route('/{id}', name: 'show', methods: ['GET'])]
    public function show(?Review $review): JsonResponse
    {
        if (!$review) {
            return $this->jsonError('Review not found.', Response::HTTP_NOT_FOUND);
        }

        return $this->json(['data' => $this->serializeReview($review)], Response::HTTP_OK);
    }

    #[Route('', name: 'create', methods: ['POST'])]
    public function create(Request $request, EntityManagerInterface $em): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_USER');

        try {
            $payload = $request->toArray();
        } catch (\JsonException) {
            return $this->jsonError('Invalid JSON body.', Response::HTTP_BAD_REQUEST);
        }

        $errors = [];
        $album = $this->extractAlbum($payload, $em, $errors);
        $rating = $this->extractRating($payload, $errors);
        $title = $this->extractOptionalTitle($payload, $errors);
        $body = $this->extractBody($payload, $errors);

        if ($errors !== []) {
            return $this->jsonValidationError($errors);
        }

        $existingReview = $em->getRepository(Review::class)->findOneBy([
            'album' => $album,
            'user' => $this->getUser(),
        ]);

        if ($existingReview) {
            return $this->jsonError(
                'You have already reviewed this album.',
                Response::HTTP_CONFLICT,
                ['existing_review_id' => $existingReview->getId()]
            );
        }

        $review = (new Review())
            ->setAlbum($album)
            ->setUser($this->getUser())
            ->setRating($rating)
            ->setTitle($title)
            ->setBody($body);

        $em->persist($review);
        $em->flush();

        return $this->json([
            'message' => 'Review created successfully.',
            'data' => $this->serializeReview($review),
        ], Response::HTTP_CREATED);
    }

    #[Route('/{id}', name: 'update_put', methods: ['PUT'])]
    public function updatePut(?Review $review, Request $request, EntityManagerInterface $em): JsonResponse
    {
        return $this->update($review, $request, $em, false);
    }

    #[Route('/{id}', name: 'update_patch', methods: ['PATCH'])]
    public function updatePatch(?Review $review, Request $request, EntityManagerInterface $em): JsonResponse
    {
        return $this->update($review, $request, $em, true);
    }

    #[Route('/{id}', name: 'delete', methods: ['DELETE'])]
    public function delete(?Review $review, EntityManagerInterface $em): JsonResponse
    {
        if (!$review) {
            return $this->jsonError('Review not found.', Response::HTTP_NOT_FOUND);
        }

        $this->denyReviewMutation($review);

        $em->remove($review);
        $em->flush();

        return $this->json(null, Response::HTTP_NO_CONTENT);
    }

    private function update(?Review $review, Request $request, EntityManagerInterface $em, bool $partial): JsonResponse
    {
        if (!$review) {
            return $this->jsonError('Review not found.', Response::HTTP_NOT_FOUND);
        }

        $this->denyReviewMutation($review);

        try {
            $payload = $request->toArray();
        } catch (\JsonException) {
            return $this->jsonError('Invalid JSON body.', Response::HTTP_BAD_REQUEST);
        }

        if ($payload === []) {
            return $this->jsonError('Request body cannot be empty.', Response::HTTP_BAD_REQUEST);
        }

        $errors = [];

        if (!$partial || array_key_exists('albumId', $payload)) {
            $album = $this->extractAlbum($payload, $em, $errors);
            if ($album) {
                $review->setAlbum($album);
            }
        }

        if (!$partial || array_key_exists('rating', $payload)) {
            $rating = $this->extractRating($payload, $errors);
            if ($rating !== null) {
                $review->setRating($rating);
            }
        }

        if (!$partial || array_key_exists('title', $payload)) {
            $title = $this->extractOptionalTitle($payload, $errors, !$partial || array_key_exists('title', $payload));
            if (array_key_exists('title', $payload)) {
                $review->setTitle($title);
            }
        }

        if (!$partial || array_key_exists('body', $payload)) {
            $body = $this->extractBody($payload, $errors);
            if ($body !== null) {
                $review->setBody($body);
            }
        }

        if ($errors !== []) {
            return $this->jsonValidationError($errors);
        }

        $duplicateReview = $em->getRepository(Review::class)->findOneBy([
            'album' => $review->getAlbum(),
            'user' => $review->getUser(),
        ]);

        if ($duplicateReview && $duplicateReview->getId() !== $review->getId()) {
            return $this->jsonError(
                'You already have a review for that album.',
                Response::HTTP_CONFLICT,
                ['existing_review_id' => $duplicateReview->getId()]
            );
        }

        $review->setUpdatedAt(new \DateTime());
        $em->flush();

        return $this->json([
            'message' => 'Review updated successfully.',
            'data' => $this->serializeReview($review),
        ], Response::HTTP_OK);
    }

    private function serializeReview(Review $review): array
    {
        return [
            'id' => $review->getId(),
            'rating' => $review->getRating(),
            'title' => $review->getTitle(),
            'body' => $review->getBody(),
            'createdAt' => $review->getCreatedAt()?->format(DATE_ATOM),
            'updatedAt' => $review->getUpdatedAt()?->format(DATE_ATOM),
            'album' => [
                'id' => $review->getAlbum()?->getId(),
                'title' => $review->getAlbum()?->getTitle(),
                'artist' => $review->getAlbum()?->getArtist(),
            ],
            'user' => [
                'id' => $review->getUser()?->getId(),
                'displayName' => $review->getUser()?->getDisplayName(),
                'email' => $review->getUser()?->getEmail(),
            ],
        ];
    }

    private function extractAlbum(array $payload, EntityManagerInterface $em, array &$errors): ?Album
    {
        if (!array_key_exists('albumId', $payload)) {
            $errors['albumId'] = 'albumId is required.';
            return null;
        }

        if (!is_int($payload['albumId']) && !ctype_digit((string) $payload['albumId'])) {
            $errors['albumId'] = 'albumId must be an integer.';
            return null;
        }

        $album = $em->getRepository(Album::class)->find((int) $payload['albumId']);
        if (!$album) {
            $errors['albumId'] = 'Album not found.';
            return null;
        }

        return $album;
    }

    private function extractRating(array $payload, array &$errors): ?int
    {
        if (!array_key_exists('rating', $payload)) {
            $errors['rating'] = 'rating is required.';
            return null;
        }

        if (!is_int($payload['rating']) && !ctype_digit((string) $payload['rating'])) {
            $errors['rating'] = 'rating must be an integer between 1 and 10.';
            return null;
        }

        $rating = (int) $payload['rating'];
        if ($rating < 1 || $rating > 10) {
            $errors['rating'] = 'rating must be between 1 and 10.';
            return null;
        }

        return $rating;
    }

    private function extractOptionalTitle(array $payload, array &$errors, bool $validatePresence = true): ?string
    {
        if (!$validatePresence && !array_key_exists('title', $payload)) {
            return null;
        }

        if (!array_key_exists('title', $payload) || $payload['title'] === null || trim((string) $payload['title']) === '') {
            return null;
        }

        if (!is_string($payload['title'])) {
            $errors['title'] = 'title must be a string.';
            return null;
        }

        $title = trim($payload['title']);
        if (mb_strlen($title) > 255) {
            $errors['title'] = 'title must not exceed 255 characters.';
            return null;
        }

        return $title;
    }

    private function extractBody(array $payload, array &$errors): ?string
    {
        if (!array_key_exists('body', $payload)) {
            $errors['body'] = 'body is required.';
            return null;
        }

        if (!is_string($payload['body']) || trim($payload['body']) === '') {
            $errors['body'] = 'body must be a non-empty string.';
            return null;
        }

        return trim($payload['body']);
    }

    private function denyReviewMutation(Review $review): void
    {
        if (!$this->getUser()) {
            throw $this->createAccessDeniedException('Authentication is required.');
        }

        if ($review->getUser() !== $this->getUser() && !$this->isGranted('ROLE_ADMIN')) {
            throw $this->createAccessDeniedException('You cannot modify this review.');
        }
    }

    private function jsonError(string $message, int $status, array $extra = []): JsonResponse
    {
        return $this->json(array_merge(['error' => $message], $extra), $status);
    }

    private function jsonValidationError(array $errors): JsonResponse
    {
        return $this->json([
            'error' => 'Validation failed.',
            'fields' => $errors,
        ], Response::HTTP_UNPROCESSABLE_ENTITY);
    }
}
