<?php

namespace App\Controller;

use App\Entity\Category;
use App\Entity\Operation;
use App\Entity\User;
use App\Repository\CategoryRepository;
use App\Repository\OperationRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class UserController extends AbstractController
{
    #[Route('/api/user/categories', name: 'get_categories', methods: ['GET'])]
    public function getCategories(CategoryRepository $categoryRepository, Security $security): JsonResponse
    {
        // Get the authenticated user
        $user = $security->getUser();

        // Fetch categories for the authenticated user
        $categories = $categoryRepository->findBy(['user' => $user]);

        // Serialize categories to JSON
        $data = [];
        foreach ($categories as $category) {
            $data[] = [
                'id' => $category->getId(),
                'title' => $category->getTitle(),
                'times' => $category->getOperations()->count(),
                'date' => $category->getDate()
            ];
        }
        dd($data);
        return $this->json($data);
    }
    #[Route('/api/user/opertaions', name: 'get_operations', methods: ['GET'])]
    public function getOpertaions(OperationRepository $operationRepository, Security $security): JsonResponse
    {
        // Get the authenticated user
        $user = $security->getUser();

        // Fetch categories for the authenticated user
        $operations = $operationRepository->findBy(['user' => $user]);

        // Serialize categories to JSON
        $data = [];
        foreach ($operations as $operation) {
            $data[] = [
                'id' => $operation->getId(),
                'title' => $operation->getLabel(),
                'emoji' => $operation->getEmoji(),
                'type' => $operation->getAction(),
                'amount' => $operation->getAmount(),
                'date' => $operation->getDate()
            ];
        }
        
        return $this->json($data);
    }
    #[Route('/api/expenses/last-month', name: 'get_last_month_expenses', methods: ['GET'])]
    public function getLast10MonthsExpenses(OperationRepository $operationRepository, Security $security): JsonResponse
    {
        // Get the authenticated user
        $user = $security->getUser();

        // Fetch expenses for the authenticated user in the last 10 months
        $expenses = $operationRepository->createQueryBuilder('o')
            ->where('o.user = :user')
            ->setParameter('user', $user)
            ->getQuery()
            ->getResult();
        // dd($expenses);
        $data = [];
        foreach ($expenses as $expense) {
            $data[] = [
                'id' => $expense->getId(),
                'title' => $expense->getLabel(),
                'type' => $expense->getAction(),
                'amount' => $expense->getAmount(),
                'date' => $expense->getDate(),
                'emoji' => $expense->getEmoji()
            ];
        }

        return $this->json($data);
    }

    #[Route('/api/update-password', name: 'update_password', methods: ['POST'])]
    public function updatePassword(
        Request $request,
        UserPasswordHasherInterface $passwordHasher,
        EntityManagerInterface $entityManager,
        ValidatorInterface $validator,
        Security $security
    ): JsonResponse {
        // Decode the JSON request
        $data = json_decode($request->getContent(), true);

        // Validate the request data
        $errors = $this->validatePasswordUpdateRequest($data, $validator);
        if (count($errors) > 0) {
            return $this->json(['errors' => $errors], Response::HTTP_BAD_REQUEST);
        }

        // Get the currently authenticated user
        $user = $security->getUser();

        // Verify the current password
        if (!$passwordHasher->isPasswordValid($user, $data['currentPassword'])) {
            return $this->json(['message' => 'Current password is incorrect'], Response::HTTP_UNAUTHORIZED);
        }

        // Hash and set the new password
        $hashedPassword = $passwordHasher->hashPassword($user, $data['newPassword']);
        $user->setPassword($hashedPassword);

        // Save the updated user
        $entityManager->persist($user);
        $entityManager->flush();

        return $this->json(['message' => 'Password updated successfully'], Response::HTTP_OK);
    }

    /**
     * Validate the password update request data.
     */
    private function validatePasswordUpdateRequest(array $data, ValidatorInterface $validator): array
    {
        $constraints = new Assert\Collection([
            'currentPassword' => [
                new Assert\NotBlank(['message' => 'Current password is required.']),
            ],
            'newPassword' => [
                new Assert\NotBlank(['message' => 'New password is required.']),
                new Assert\Length(['min' => 6, 'minMessage' => 'New password must be at least 6 characters long.']),
            ],
        ]);

        $errors = $validator->validate($data, $constraints);
        $errorMessages = [];
        foreach ($errors as $error) {
            $errorMessages[$error->getPropertyPath()] = $error->getMessage();
        }

        return $errorMessages;
    }

    // custome mehtod to add new operation
    #[Route('/api/new/operation', name: 'add_operation', methods: ['POST'])]
    public function addOperation(Request $request, EntityManagerInterface $entityManager, ValidatorInterface $validator, Security $security): JsonResponse
    {
        // Decode the JSON payload
        $data = json_decode($request->getContent(), true);

        // Validate required fields
        if (!isset($data['label'], $data['amount'], $data['date'], $data['action'], $data['category_id'])) {
            return new JsonResponse(['error' => 'Missing required fields'], 400);
        }

        // Create a new Operation entity
        $operation = new Operation();
        $operation->setLabel($data['label']);
        $operation->setAmount($data['amount']);
        $operation->setDate(new \DateTime($data['date']));
        $operation->setAction($data['action']);
        $operation->setEmoji($data['emoji']);

        // Fetch and set the Category entity
        $category = $entityManager->getRepository(Category::class)->find($data['category_id']);
        if (!$category) {
            return new JsonResponse(['error' => 'Category not found'], 404);
        }
        $operation->setCategory($category);

        // Fetch and set the User entity
        $user = $security->getUser();
        $operation->setUser($user);

        // Validate the Operation entity
        $errors = $validator->validate($operation);
        if (count($errors) > 0) {
            $errorMessages = [];
            foreach ($errors as $error) {
                $errorMessages[] = $error->getMessage();
            }
            return new JsonResponse(['errors' => $errorMessages], 400);
        }

        // Persist and flush the Operation entity
        $entityManager->persist($operation);
        $entityManager->flush();

        // Return a success response
        return new JsonResponse([
            'status' => 'Operation created successfully',
            'operation_id' => $operation->getId(),
        ], 201);
    }

    // custome mehtod to add new category
    #[Route('/api/custom/categorie', name: 'custome_category', methods: ['POST'])]
    public function addCategory(Request $request, EntityManagerInterface $entityManager, ValidatorInterface $validator, Security $security): JsonResponse
    {
        // Decode the JSON payload
        $data = json_decode($request->getContent(), true);

        // Validate required fields
        if (!isset($data['title'])) {
            return new JsonResponse(['error' => 'Missing required fields: title and user_id'], 400);
        }

        // Create a new Category entity
        $category = new Category();
        $category->setTitle($data['title']);

        // Fetch and set the User entity
        $user = $security->getUser();
        $category->setUser($user);
        $now = new \DateTime();
        $category->setDate($now);

        // Validate the Category entity
        $errors = $validator->validate($category);
        if (count($errors) > 0) {
            $errorMessages = [];
            foreach ($errors as $error) {
                $errorMessages[] = $error->getMessage();
            }
            return new JsonResponse(['errors' => $errorMessages], 400);
        }

        // Persist and flush the Category entity
        $entityManager->persist($category);
        $entityManager->flush();

        // Return a success response
        return new JsonResponse([
            'status' => 'Category created successfully',
            'category_id' => $category->getId(),
        ], 201);
    }
}
