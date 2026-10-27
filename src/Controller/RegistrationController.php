<?php

namespace App\Controller;

use App\Entity\User;
use App\Form\UserEditFormType;
use App\Security\EmailVerifier;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Translation\TranslatorInterface;
use SymfonyCasts\Bundle\VerifyEmail\Exception\VerifyEmailExceptionInterface;

class RegistrationController extends AbstractController
{
    public function __construct(private EmailVerifier $emailVerifier)
    {
    }

    // Plus d'inscription publique (/register supprime) : les comptes sont crees par un admin via users.edit
    #[Route('/verify/email', name: 'app_verify_email')]
    public function verifyUserEmail(Request $request, TranslatorInterface $translator, EntityManagerInterface $entityManager): Response
    {
        $id = $request->query->get('id');
        
        if (null === $id) {
            return $this->redirectToRoute('app_login');
        }

        $user = $entityManager->getRepository(User::class)->find($id);

        if (null === $user) {
            return $this->redirectToRoute('app_login');
        }

        // validate email confirmation link, sets User::isVerified=true and persists
        try {
            $this->emailVerifier->handleEmailConfirmation($request, $user);
        } catch (VerifyEmailExceptionInterface $exception) {
            $this->addFlash('verify_email_error', $translator->trans($exception->getReason(), [], 'VerifyEmailBundle'));

            return $this->redirectToRoute('app_login');
        }

        $this->addFlash('success', 'Votre adresse email a été vérifiée avec succès. Vous pouvez maintenant vous connecter.');

        return $this->redirectToRoute('app_login');
    }
    #[Route('/users/{page?1}/{nbre?15}', name: 'users.list')]
    public function indexAlls(ManagerRegistry $doctrine,$page,$nbre): Response
    {
        
        $this->denyAccessUnlessGranted('ROLE_ADMIN');
       $repository = $doctrine->getRepository(User::class);
     
       $utilisateurs = $repository->findBy([],['nom'=>'ASC'],$nbre,($page-1)*$nbre);
        return $this->render('registration/users.html.twig', [
            'utilisateurs' => $utilisateurs,'isPaginated'=>true,
            
        ]);
    }

    #[Route('/user/delete/{id}', name: 'users.delete')]
    public function deleteUser(ManagerRegistry $doctrine,$id): RedirectResponse
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');
        $repository = $doctrine->getRepository(User::class);
        $utilisateur = $repository->find($id);
        if($utilisateur){
            $manager = $doctrine->getManager();
            $manager->remove($utilisateur);
            $manager->flush();
            $this->addFlash(
               'success',
               "L'utilisateur a été supprimé avec succès"
            );
        }else{
            $this->addFlash(
                'error',
                "L'utilisateur demandé n'existe pas"
             );
        }
        return $this->redirectToRoute('users.list');
        
    }

    #[Route('/user/edit/{id?0}', name: 'users.edit')]
    public function addUser(ManagerRegistry $doctrine, Request $request, UserPasswordHasherInterface $userPasswordHasher, $id): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');
        $repository = $doctrine->getRepository(User::class);
        $utilisateur = $repository->find($id);
        $new = false;
        if(!$utilisateur){
            $utilisateur = new User();
            // Coche "verifie" par defaut : un compte non verifie ne peut pas se connecter
            $utilisateur->setVerified(true);
            $new = true;
        }
        
       $form = $this->createForm(UserEditFormType::class, $utilisateur);
       $form->handleRequest($request);
       
       if($form->isSubmitted() && $form->isValid()){
        // Handle optional password change
        $plainPassword = $form->get('plainPassword')->getData();
        if ($plainPassword) {
            $utilisateur->setPassword($userPasswordHasher->hashPassword($utilisateur, $plainPassword));
        } elseif ($new) {
            // New user must have a password - set a temporary one if empty
            $this->addFlash('error', 'Le mot de passe est obligatoire pour un nouvel utilisateur.');
            return $this->render('registration/edit_user.html.twig', [
                'userForm' => $form->createView(),
                'user' => $utilisateur,
                'isNew' => $new,
            ]);
        }

        if ($new) {
            $message = "L'utilisateur est ajouté avec succès";
        } else {
            $message = "L'utilisateur a été mis à jour avec succès";
        }
        $entityManager = $doctrine->getManager();
        $entityManager->persist($utilisateur);
        $entityManager->flush();
        
        $this->addFlash('success', $message);
        return $this->redirectToRoute('users.list');
       } else {
            return $this->render('registration/edit_user.html.twig', [
                'userForm' => $form->createView(),
                'user' => $utilisateur,
                'isNew' => $new,
            ]);
       }
        
    }
}
