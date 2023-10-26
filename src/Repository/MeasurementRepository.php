<?php

namespace App\Repository;

use App\Entity\Measurement;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Measurement>
 *
 * @method Measurement|null find($id, $lockMode = null, $lockVersion = null)
 * @method Measurement|null findOneBy(array $criteria, array $orderBy = null)
 * @method Measurement[]    findAll()
 * @method Measurement[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class MeasurementRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Measurement::class);
    }

    public function save(Measurement $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(Measurement $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function findMeasurements24h($user): array
    {
        $qb = $this->createQueryBuilder('m');
        $qb->select('m')
            ->where('m.user = :user')
            ->andWhere('m.timestamp >= :date')
            ->setParameters([
                'user' => $user,
                'date' => new \DateTime('-24 hours')
            ])
            ->orderBy('m.timestamp', 'DESC')
            ->setMaxResults(86400);

        return array_reverse($qb->getQuery()->getResult());
    }


    public function findMeasurements5m(string $user): array
    {
        $qb = $this->createQueryBuilder('m');
        $qb->select('m')
            ->where('m.timestamp >= :date')
            ->setParameter('date', new \DateTime('-5 minutes'))
            ->orderBy('m.timestamp', 'DESC');

        return $qb->getQuery()->getResult();
    }

    public function findMeasurementsLastXAmount(User $user, int $amount): array
    {
        // Create query builder and get the last X measurements for the specified user
        $qb = $this->createQueryBuilder('m');
        $qb->select('m')
            ->where('m.user = :user')
            ->setParameter('user', $user)
            ->orderBy('m.timestamp', 'DESC')
            ->setMaxResults($amount);

        // Return result
        return $qb->getQuery()->getResult();
    }


//    /**
//     * @return Measurement[] Returns an array of Measurement objects
//     */
//    public function findByExampleField($value): array
//    {
//        return $this->createQueryBuilder('m')
//            ->andWhere('m.exampleField = :val')
//            ->setParameter('val', $value)
//            ->orderBy('m.id', 'ASC')
//            ->setMaxResults(10)
//            ->getQuery()
//            ->getResult()
//        ;
//    }

//    public function findOneBySomeField($value): ?Measurement
//    {
//        return $this->createQueryBuilder('m')
//            ->andWhere('m.exampleField = :val')
//            ->setParameter('val', $value)
//            ->getQuery()
//            ->getOneOrNullResult()
//        ;
//    }
}
