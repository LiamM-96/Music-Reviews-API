<?php

namespace App\Repository;

use App\Entity\Album;
use App\Entity\Genre;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class AlbumRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Album::class);
    }

    /**
     * Search / filter albums.
     *
     * @return Album[]
     */
    public function searchAlbums(
        ?string $q,
        ?Genre $genre,
        ?int $yearFrom,
        ?int $yearTo
    ): array {
        $qb = $this->createQueryBuilder('a')
            ->leftJoin('a.genre', 'g')
            ->addSelect('g')
            ->orderBy('a.createdAt', 'DESC');

        if ($q !== null && $q !== '') {
            $qb->andWhere('a.title LIKE :q OR a.artist LIKE :q')
               ->setParameter('q', '%' . $q . '%');
        }

        if ($genre !== null) {
            $qb->andWhere('a.genre = :genre')
               ->setParameter('genre', $genre);
        }

        if ($yearFrom !== null) {
            $qb->andWhere('a.releaseYear >= :yearFrom')
               ->setParameter('yearFrom', $yearFrom);
        }

        if ($yearTo !== null) {
            $qb->andWhere('a.releaseYear <= :yearTo')
               ->setParameter('yearTo', $yearTo);
        }

        return $qb->getQuery()->getResult();
    }
}
