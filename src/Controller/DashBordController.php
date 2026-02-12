<?php

namespace App\Controller;

use App\Repository\EntetepieceRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class DashBordController extends AbstractController
{
    #[Route('/dashbord', name: 'app_dash_bord')]
    public function index(EntetepieceRepository $repositoryPiece): Response
    {
        $montantN = array(
            0 => 0,
            1 => 0,
            2 => 0,
            3 => 0,
            4 => 0,
            5 => 0,
            6 => 0,
            7 => 0,
            8 => 0,
            9 => 0,
            10 => 0,
            11 => 0,
        );
        $montantN1 = array(
            0 => 0,
            1 => 0,
            2 => 0,
            3 => 0,
            4 => 0,
            5 => 0,
            6 => 0,
            7 => 0,
            8 => 0,
            9 => 0,
            10 => 0,
            11 => 0,
        );
        $CaAnneeMois = $repositoryPiece->getCaParAnnee(0);            
        $CaAnneeMoisN1 = $repositoryPiece->getCaParAnnee(1); 
        
        for ($i = 0; $i < 12; $i++){
            if(isset($CaAnneeMois[$i])){
                $montantN[$i] = $CaAnneeMois[$i]["mont"];
            }
            if(isset($CaAnneeMoisN1[$i])){
                $montantN1[$i] = $CaAnneeMoisN1[$i]["mont"];
            }
        }
        

        return $this->render('dash_bord/index.html.twig', [
            'controller_name' => 'DashBordController',
            'montantJanv'=> $montantN[0],
            'montantFev'=> $montantN[1],
            'montantMars'=> $montantN[2],
            'montantAvr'=> $montantN[3],
            'montantMai'=> $montantN[4],
            'montantJuin'=> $montantN[5],
            'montantJuil'=> $montantN[6],
            'montantAout'=> $montantN[7],
            'montantSept'=> $montantN[8],
            'montantOct'=> $montantN[9],
            'montantNov'=> $montantN[10],
            'montantDec'=> $montantN[11],
            'montantJanvN1'=> $montantN1[0],
            'montantFevN1'=> $montantN1[1],
            'montantMarsN1'=> $montantN1[2],
            'montantAvrN1'=> $montantN1[3],
            'montantMaiN1'=> $montantN1[4],
            'montantJuinN1'=> $montantN1[5],
            'montantJuilN1'=> $montantN1[6],
            'montantAoutN1'=> $montantN1[7],
            'montantSeptN1'=> $montantN1[8],
            'montantOctN1'=> $montantN1[9],
            'montantNovN1'=> $montantN1[10],
            'montantDecN1'=> $montantN1[11]
        ]);
    }
}
