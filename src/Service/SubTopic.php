<?php

namespace App\Service;

use App\Repository\SubcategoryRepository;
use App\Repository\TopicRepository;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class SubTopic extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry) { 
        parent::__construct($registry, SubcategoryRepository::class); 
        } 
    public function Sub(string $title) {

         $subcatRepo = new SubcategoryRepository();
        return $subcatRepo->findOneBy(['title' => $title]);
    }
    public function TopicId(int $id, TopicRepository $topicRepository) {
        
        return $topicRepository->findOneBy(['id' => $id]);
    }
}