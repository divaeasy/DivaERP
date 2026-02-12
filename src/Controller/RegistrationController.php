<?php

namespace App\Controller;

use App\Entity\User;
use App\Form\RegistrationFormType;
use App\Security\EmailVerifier;
use App\Security\LoginAuthenticator;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mime\Address;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Translation\TranslatorInterface;
use SymfonyCasts\Bundle\VerifyEmail\Exception\VerifyEmailExceptionInterface;

class RegistrationController extends AbstractController
{
    public function __construct(private EmailVerifier $emailVerifier)
    {
    }

    #[Route('/register', name: 'app_register')]
    public function register(Request $request, UserPasswordHasherInterface $userPasswordHasher, Security $security, EntityManagerInterface $entityManager): Response
    {
        $user = new User();
        $form = $this->createForm(RegistrationFormType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var string $plainPassword */
            $plainPassword = $form->get('plainPassword')->getData();

            // encode the plain password
            $user->setPassword($userPasswordHasher->hashPassword($user, $plainPassword));

            $entityManager->persist($user);
            $entityManager->flush();

            // generate a signed url and email it to the user
            $this->emailVerifier->sendEmailConfirmation('app_verify_email', $user,
                (new TemplatedEmail())
                    ->from(new Address('hassan.oukajji@gmail.com', 'Hassan'))
                    ->to((string) $user->getEmail())
                    ->subject('Please Confirm your Email')
                    ->htmlTemplate('registration/confirmation_email.html.twig')
            );

            // do anything else you need here, like send an email

            return $security->login($user, LoginAuthenticator::class, 'main');
        }

        return $this->render('registration/register.html.twig', [
            'registrationForm' => $form,
        ]);
    }

    #[Route('/verify/email', name: 'app_verify_email')]
    public function verifyUserEmail(Request $request, TranslatorInterface $translator): Response
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');

        // validate email confirmation link, sets User::isVerified=true and persists
        try {
            /** @var User $user */
            $user = $this->getUser();
            $this->emailVerifier->handleEmailConfirmation($request, $user);
        } catch (VerifyEmailExceptionInterface $exception) {
            $this->addFlash('verify_email_error', $translator->trans($exception->getReason(), [], 'VerifyEmailBundle'));

            return $this->redirectToRoute('app_register');
        }

        // @TODO Change the redirect on success and handle or remove the flash message in your templates
        $this->addFlash('success', 'Your email address has been verified.');

        return $this->redirectToRoute('app_register');
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
    public function addUser(ManagerRegistry $doctrine, Request $request, $id): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');
        $repository = $doctrine->getRepository(User::class);
        $utilisateur = $repository->find($id);
        $new = false;
        if(!$utilisateur){
            $utilisateur = new User();
            $new = true;
        }
        
       $form = $this->createForm(RegistrationFormType::class, $utilisateur);
       $form->handleRequest($request);
       
       if($form->isSubmitted() && $form->isValid()){

        If ($new){
            $message = "L'utilisateur est ajouté avec succès";
            //$adherent->setCreatedBy($this->getUser());
            //$adherent->setCreatedAt(new \DateTimeImmutable('now'));
        }else{
            $message = "L'utilisateur a été mis à jour avec succès";
            //$adherent->setModifedBy($this->getUser());
            //$adherent->setModifedAt(new \DateTimeImmutable('now'));
        }
        $entityManager = $doctrine->getManager();
        $entityManager->persist($utilisateur);
        $entityManager->flush();
        
        //$mailMessage = $adherent->getNom() . ' ' . $adherent->getPrenom().' '.$message;
        //$mailerService->sendEmail('hassan.oukajji@gmail.com','',$mailMessage);
        $this->addFlash(
           'success',
           $message
        );
        return $this->redirectToRoute('users.list');
       }else{
            return $this->render('registration/register.html.twig', [
                //'adherent' => $adherent,
                'registrationForm'=>$form->createView()
            ]);
       }
        
    }
}
