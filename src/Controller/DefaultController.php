<?php

namespace App\Controller;

use App\Entity\Commande;
use App\Form\CommandeType;
use App\Entity\JourDistrib;
use App\Entity\LigneCommande;

use App\Repository\CommandeRepository;
use App\Repository\JourDistribRepository;
use App\Repository\ProductRepository;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

use Symfony\Component\HttpFoundation\Cookie;

class DefaultController extends AbstractController
{
    private $session;

    public function __construct(SessionInterface $session)
    {
        $this->session = $session;
    }
    /**
     * @Route("/", name="passe_commande_index", methods={"GET"})
     */
    public function index(JourDistribRepository $jourDistribRepository): Response
    {
        $jourDistribs = $jourDistribRepository->findAllActive();

        return $this->render('passe_commande/index.html.twig', [
            'lastNom' => $this->session->get('commande_nom'),
            'lastPrenom' => $this->session->get('commande_prenom'),
            'jour_distribs' => $jourDistribs,
            'poid_restant' => $jourDistribRepository->findConditionnement(),
        ]);
    }

    /**
     * @Route("/synthese/{suivi}", name="synthese_index", methods={"GET"})
     */
    public function synthese(JourDistribRepository $jourDistribRepository, int $suivi ): Response
    {
        switch ($suivi) {
            case 0:
                $jourDistribs = $jourDistribRepository->findAllActive();
                break;
            case 1:
                $order = 'DESC';
                $jourDistribs = $jourDistribRepository->findAllOrder($order);
                break;
            case 2:
                $order = 'DESC';
                $jourDistribs = $jourDistribRepository->findAllOrder($order);
                break;
            default:
                $order = 'ASC';
                $jourDistribs = null;
                break;
        }
        return $this->render('passe_commande/synthese.html.twig', [
            'jour_distribs' => $jourDistribs,
            'suivi' => $suivi
        ]);
    }
    /**
     * @Route("/livraison/{type}", name="livraison", methods={"GET"})
     */
    public function livraison(JourDistribRepository $jourDistribRepository, string $type): Response
    {
        $jourDistribs = $jourDistribRepository->findAllActive();
        
        if ($type == 'product')
        {
            $jourDistribsTable = [];
            foreach ($jourDistribs as $jourDistrib ) {
                dump($jourDistrib->getId());
                $products = $jourDistrib->getProducts();
                $productTable = [];
                $i=0;
                foreach ($products as $product ) {;
                    $ligneCommandes = $product->getLigneCommandes();
                    $ligneCommndeTable = [];
                    foreach ($ligneCommandes as $ligneCommande ) {
                        if($ligneCommande->getCommande()->getJourDistrib() == $jourDistrib and $ligneCommande->getLivree() == false and $ligneCommande->getCommande()->getLivree() == false and $ligneCommande->getCommande()->getConfirmed() == true)
                        {
                            array_push($ligneCommndeTable, $ligneCommande);
                        }
                    }
                    array_push($productTable, ['id' => $i,'nom'=>$product->getNom(), 'lignesCommande' => $ligneCommndeTable]);
                    $i++;
                }
                array_push($jourDistribsTable, ['date' => $jourDistrib->getDate(), 'produits' => $productTable]);
            }


            return $this->render('passe_commande/livraison_by_product.html.twig', [
                'jour_distribs' => $jourDistribsTable,
            ]);
        }
        elseif ($type == 'command') {
            return $this->render('passe_commande/livraison_by_command.html.twig', [
                'jour_distribs' => $jourDistribs,
            ]);
        }
    }

    /**
     * @Route("/livree/{commande}", name="livree_commande", methods={"GET"})
     */
    public function livreeCommande(CommandeRepository $commandeRepository, Commande $commande ): Response
    {
        $entityManager = $this->getDoctrine()->getManager();
        $commande->setLivree(true);
        foreach ($commande->getLigneCommandes() as $ligneCommande) {
            $ligneCommande->setLivree(true);
            $entityManager->persist($ligneCommande);
            $entityManager->flush();
        }
        $entityManager->persist($commande);
        $entityManager->flush();

        return $this->redirectToRoute('synthese_index',['suivi' => 1]);
    }

    /**
     * @Route("/livree/ligne/{ligneCommande}", name="livree_ligne_commande", methods={"GET"})
     */
    public function livreeLigneCommande(CommandeRepository $commandeRepository, LigneCommande $ligneCommande ): Response
    {
        $entityManager = $this->getDoctrine()->getManager();
        $ligneCommande->setLivree(true);
        $entityManager->persist($ligneCommande);
        $entityManager->flush();
        $testLigneNonLivree = $entityManager->getRepository(Commande::class)->testLigneNonLivree($ligneCommande->getCommande());

        if ( intval($testLigneNonLivree) == 0 )
        {
            $ligneCommande->getCommande()->setLivree(true);
        }
        $entityManager->persist($ligneCommande);
        $entityManager->flush();

        return $this->redirectToRoute('livraison',['type' => 'product']);
    }

    /**
     * @Route("/confirme/{commande}", name="livree_confirme", methods={"GET"})
     */
    public function confirmeCommande(CommandeRepository $commandeRepository, Commande $commande ): Response
    {
        $entityManager = $this->getDoctrine()->getManager();
        $commande->setConfirmed(true);
        
        $entityManager->persist($commande);
        $entityManager->flush();

        return $this->redirectToRoute('synthese_index',['suivi' => 2]);
    }

    /**
     * @Route("/new/{idJourDistrib}", name="passe_commande_new", methods={"GET","POST"})
     */
    public function new(Request $request, int $idJourDistrib, JourDistribRepository $jourDistribRepository): Response
    {
        $commande = new Commande();
        $jourDistrib = $jourDistribRepository->findOneById($idJourDistrib);
        $products = $jourDistrib->getProducts();
        if ( $jourDistrib->getClosed() === false ) {

            $request = Request::createFromGlobals();
            $cookie = $request->cookies->get('commande');
            $nom = "";
            $prenom = "";
            if (isset($cookie)) {
                $contentCookie = json_decode($cookie);
                $nom = $contentCookie->nom;
                $prenom = $contentCookie->prenom;
            }
    
            $form = $this->createForm(CommandeType::class, $commande, [
                'products' => $products, 
                'idJourDistrib' => $idJourDistrib, 
                'jourDistrib' => $jourDistrib,
                'lastNom' => $nom,
                'lastPrenom' => $prenom,
                ]);
            $form->handleRequest($request);
            
            if ($form->isSubmitted() && $form->isValid()) {

                // On récupère la somme des poids des product de la commande
                $poidCommande = 0;
                foreach ($form->getData()->getLigneCommandes() as $ligneCommande ){
                    $poidCommande += $ligneCommande->getproduct()->getConditionnement() * floatval($ligneCommande->getQuantite());
                }
                
                // On additionne avec le poid restant du jour
                $poidRestant = $form->getData()->getJourDistrib()->getPoidRestant();
                $poidRestant += $poidCommande;
                
                if ($poidRestant <= $form->getData()->getJourDistrib()->getTotal() or $jourDistrib->getTotal() == 0) {
                    
                    $form->getData()->getJourDistrib()->setPoidRestant($poidRestant);
    
                    $entityManager = $this->getDoctrine()->getManager();
                    $commande->setDate(new \DateTime(date("Y-m-d H:i:s")));
                    $entityManager->persist($commande);
                    $entityManager->flush();
    
                    $cookieValue = [
                        'command_id' => $commande->getId(),
                        'nom' => $commande->getNom(),
                        'prenom' => $commande->getPrenom(),
                    ];
    
                    $coockie = new Cookie('commande', json_encode($cookieValue), time() + ( 2 * 365 * 24 * 60 * 60));
                    $response = $this->redirectToRoute('commande_index');
                    $response->headers->setCookie($coockie);
    
                    $this->addFlash(
                        'success',
                        'La commande a été enregistrée'
                    );            
                        
                    return $response;
                }
                else {
                    $this->addFlash(
                        'warning',
                        'La limite de poid disponible a été dépassée !'
                    );
                    return $this->redirectToRoute('passe_commande_index');
                }
                
            }
    
            return $this->render('commande/new.html.twig', [
                'commande' => $commande,
                'form' => $form->createView(),
                'products' => $products,
                'idJourDistrib' => $jourDistrib,
            ]);
        } 
        else {
            $this->addFlash(
                'warning',
                'La commande est fermée'
            );
            return $this->redirectToRoute('passe_commande_index');
        }

    }

}