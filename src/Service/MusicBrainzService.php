<?php

namespace App\Service;

use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;

class MusicBrainzService
{
    public function __construct(
        private readonly ClientInterface $client,
    ) {
    }

    public function getAlbumContext(string $artist, string $title): ?array
    {
        $releaseGroup = $this->findReleaseGroup($artist, $title);
        if (!$releaseGroup) {
            return null;
        }

        $artistInfo = $this->findArtist($artist);
        $coverArt = $this->findCoverArtForReleaseGroup($releaseGroup['id'] ?? null);

        return [
            'releaseGroup' => [
                'id' => $releaseGroup['id'] ?? null,
                'title' => $releaseGroup['title'] ?? null,
                'primaryType' => $releaseGroup['primary-type'] ?? null,
                'firstReleaseDate' => $releaseGroup['first-release-date'] ?? null,
                'disambiguation' => $releaseGroup['disambiguation'] ?? null,
                'tags' => array_slice(array_map(
                    static fn (array $tag) => $tag['name'] ?? null,
                    $releaseGroup['tags'] ?? []
                ), 0, 5),
                'musicBrainzUrl' => isset($releaseGroup['id'])
                    ? sprintf('https://musicbrainz.org/release-group/%s', $releaseGroup['id'])
                    : null,
            ],
            'artist' => [
                'id' => $artistInfo['id'] ?? null,
                'name' => $artistInfo['name'] ?? null,
                'country' => $artistInfo['country'] ?? null,
                'type' => $artistInfo['type'] ?? null,
                'disambiguation' => $artistInfo['disambiguation'] ?? null,
            ],
            'coverArt' => $coverArt,
        ];

        dd($result);
    }

    private function findReleaseGroup(string $artist, string $title): ?array
    {
        $query = sprintf('releasegroup:"%s" AND artist:"%s"', $title, $artist);

        try {
            $response = $this->client->request('GET', 'https://musicbrainz.org/ws/2/release-group', [
                'query' => [
                    'query' => $query,
                    'fmt' => 'json',
                    'limit' => 1,
                ],
                'headers' => [
                    'Accept' => 'application/json',
                    'User-Agent' => 'AlbumReviewCoursework/1.0 (l.metcalfe@edu.salford.ac.uk)',
                ],
            ]);

            $data = json_decode((string) $response->getBody(), true);
            // dd($data['release-groups'][0]);
            return $data['release-groups'][0] ?? null;
        } catch (GuzzleException|\JsonException) {
            return null;
        }
    }

    private function findArtist(string $artist): ?array
    {
        try {
            $response = $this->client->request('GET', 'https://musicbrainz.org/ws/2/artist', [
                'query' => [
                    'query' => sprintf('artist:"%s"', $artist),
                    'fmt' => 'json',
                    'limit' => 1,
                ],
                'headers' => [
                    'Accept' => 'application/json',
                    'User-Agent' => 'AlbumReviewCoursework/1.0 (l.metcalfe@edu.salford.ac.uk)',
                ],
            ]);

            $data = json_decode((string) $response->getBody(), true);
            return $data['artists'][0] ?? null;
        } catch (GuzzleException|\JsonException) {
            return null;
        }
    }

    private function findCoverArtForReleaseGroup(?string $releaseGroupId): ?array
    {
        if (!$releaseGroupId) {
            return null;
        }

        try {
            $response = $this->client->request('GET', sprintf('https://coverartarchive.org/release-group/%s', $releaseGroupId), [
                'headers' => [
                    'Accept' => 'application/json',
                    'User-Agent' => 'AlbumReviewCoursework/1.0 (l.metcalfe@edu.salford.ac.uk)',
                ],
            ]);

            $data = json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR);
            $firstImage = $data['images'][0] ?? null;

            if (!$firstImage) {
                return null;
            }

            return [
                'thumbnail' => $firstImage['thumbnails']['small'] ?? null,
                'image' => $firstImage['image'] ?? null,
                'front' => $firstImage['front'] ?? false,
            ];
        } catch (GuzzleException|\JsonException) {
            return null;
        }
    }
}
