<?php

namespace App\Controller;

use App\Repository\CategoryRepository;
use App\Repository\OperationRepository;
use App\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class PlayingController extends AbstractController
{
    private UserRepository $userRepository;
    public function __construct(UserRepository $userRepository)
    {
        $this->userRepository = $userRepository;
    }
    #[Route('/api/playing/{id}', name: 'app_playing')]
    public function getCategories(OperationRepository $operationRepository, Security $security, String $id): JsonResponse
    {
        // Get the authenticated user
        $user = $security->getUser();
        // $user = $this->userRepository->find($id);

        // Fetch categories for the authenticated user
        $categories = $operationRepository->findBy(['user' => $user]);
        // Serialize categories to JSON
        $data = [];
        $total = 0;
        foreach ($categories as $category) {
            $data[] = [
                'id' => $category->getId(),
                'title' => $category->getLabel(),
                'amount' => $category->getAmount(),
            ];
            $total += $category->getAmount();
        }

        return $this->json(['data' => $data, "counte" => count($data), "total" => $total]);
    }
}
