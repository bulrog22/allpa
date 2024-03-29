<?php

namespace App\Controller;

use App\Entity\Commande;
use App\Form\CommandeType;
use App\Entity\JourDistrib;
use App\Entity\LigneCommande;
use App\Entity\Settings;

use App\Repository\CommandeRepository;
use App\Repository\JourDistribRepository;
use App\Repository\ProductRepository;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

use Symfony\Contracts\Translation\TranslatorInterface;

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
        $tableJours = [];

        foreach ($jourDistribs as $jourDistrib) 
        {
            $testUser = false;
            if ($jourDistrib->getLimite() == true)
            {
                foreach ($jourDistrib->getCommandes() as $commande)
                {
                    if ($commande->getUser() == $this->getUser())
                    {
                        $testUser = true;
                    }
                }
            }
            array_push ($tableJours, ['jour' => $jourDistrib, 'test_user_commande' => $testUser]);
        }
        
        return $this->render('passe_commande/index.html.twig', [
            'jour_distribs' => $tableJours,
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
                $jourDistribs = $jourDistribRepository->findAllUser($this->getUser());
                // dump ($jourDistribs);
                // die;
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
        $jourDistribs = $jourDistribRepository->findAllActiveByCommand();
        
        if ($type == 'product')
        {
            $jourDistribsTable = [];
            foreach ($jourDistribs as $jourDistrib ) {
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

        return $this->redirectToRoute('livraison',['type' => "command"]);
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
    public function confirmeCommande(CommandeRepository $commandeRepository, Commande $commande, MailerInterface $mailer, TranslatorInterface $translator): Response
    {
        $entityManager = $this->getDoctrine()->getManager();
        $commande->setConfirmed(true);
        
        $entityManager->persist($commande);
        $entityManager->flush();
        $messageEmail = $entityManager->getRepository(Settings::class)->findOneByName('text_confirm_email')->getValue();
        $messageEmail .= '</br></br><table style="border-collapse: collapse; border: 1px solid black">';
        $ligneCommandes = $commande->getLigneCommandes();
        $messageEmail .= '<tr>';
        $messageEmail .= '<th style="border: 1px solid black">Produit</th>';
        $messageEmail .= '<th style="border: 1px solid black">Conditionnement</th>';
        $messageEmail .= '<th style="border: 1px solid black">Prix unitaire initial</th>';
        $messageEmail .= '<th style="border: 1px solid black">Quantité</th>';
        $messageEmail .= '</tr>';
        foreach ($ligneCommandes as $ligneCommande) {
            $messageEmail .= '<tr>';
            $messageEmail .= '<td style="border: 1px solid black">' . $ligneCommande->getProduct()->getNom() . '</td>';
            $messageEmail .= '<td style="border: 1px solid black">' . $ligneCommande->getProduct()->getConditionnement() . $ligneCommande->getProduct()->getUnit() . '</td>';
            $messageEmail .= '<td style="border: 1px solid black">' . $ligneCommande->getProduct()->getPrixInit() . ' €' . '</td>';
            $messageEmail .= '<td style="border: 1px solid black">' . $ligneCommande->getQuantite() . '</td>';
            $messageEmail .= '</tr>';
        }
        $messageEmail .= '</table>';

        $email = (new Email())
            ->from($entityManager->getRepository(Settings::class)->findOneByName('contact_email')->getValue())
            ->to($commande->getUser()->getMail())
            //->cc('cc@example.com')
            //->bcc('bcc@example.com')
            //->replyTo('fabien@example.com')
            //->priority(Email::PRIORITY_HIGH)
            ->subject($translator->trans('email.subject.confirm_command'))
            // ->text($messageEmail)
            ->html($messageEmail);

        $mailer->send($email);

        return $this->redirectToRoute('synthese_index',['suivi' => 2]);
    }

    /**
     * @Route("/new/{idJourDistrib}", name="passe_commande_new", methods={"GET","POST"})
     */
    public function new(Request $request, int $idJourDistrib, JourDistribRepository $jourDistribRepository, MailerInterface $mailer, TranslatorInterface $translator): Response
    {
        $commande = new Commande();
        $jourDistrib = $jourDistribRepository->findOneById($idJourDistrib);
        $products = $jourDistrib->getProducts();
        if ( $jourDistrib->getClosed() === false ) {
    
            $form = $this->createForm(CommandeType::class, $commande, [
                'products' => $products, 
                'idJourDistrib' => $idJourDistrib, 
                'jourDistrib' => $jourDistrib,
                ]);
            $form->handleRequest($request);
            
            if ($form->isSubmitted() && $form->isValid()) {

                // Vérification de commande déjà passée
                if ($jourDistrib->getLimite())
                {
                    $testCommande = $jourDistribRepository->findCommande($this->getUser(), $jourDistrib);
                }
                // On récupère la somme des poids des product de la commande
                $poidCommande = 0;
                foreach ($form->getData()->getLigneCommandes() as $ligneCommande ){
                    $poidCommande += $ligneCommande->getproduct()->getConditionnement() * floatval($ligneCommande->getQuantite());
                }
                
                // Si l'utilisateur n'as pas passé de commande
                if (!$testCommande)
                {
                    // On additionne avec le poid restant du jour
                    $poidRestant = $form->getData()->getJourDistrib()->getPoidRestant();
                    $poidRestant += $poidCommande;
                    
                    if ($poidRestant <= $form->getData()->getJourDistrib()->getTotal() or $jourDistrib->getTotal() == 0) {
                        
                        $form->getData()->getJourDistrib()->setPoidRestant($poidRestant);
                        
                        $entityManager = $this->getDoctrine()->getManager();
                        $commande->setDate(new \DateTime(date("Y-m-d H:i:s")));
                        $commande->setUser($this->getUser());
                        $entityManager->persist($commande);
                        $entityManager->flush();
        
                        $response = $this->redirectToRoute('commande_index');
    
                        $textRegisterCommand = $entityManager->getRepository(Settings::class)->findOneByName('text_register_command')->getValue();
    
                        $this->addFlash(
                            'success',
                            'Attention ! ' . $textRegisterCommand
                        );            
                            
                        $messageEmail = $entityManager->getRepository(Settings::class)->findOneByName('text_register_command')->getValue();
                        $messageEmail .= '</br></br><table style="border-collapse: collapse; border: 1px solid black">';
                        $ligneCommandes = $commande->getLigneCommandes();
                        $messageEmail .= '<tr>';
                        $messageEmail .= '<th style="border: 1px solid black">Produit</th>';
                        $messageEmail .= '<th style="border: 1px solid black">Conditionnement</th>';
                        $messageEmail .= '<th style="border: 1px solid black">Prix unitaire initial</th>';
                        $messageEmail .= '<th style="border: 1px solid black">Quantité</th>';
                        $messageEmail .= '</tr>';
                        foreach ($ligneCommandes as $ligneCommande) {
                            $messageEmail .= '<tr>';
                            $messageEmail .= '<td style="border: 1px solid black">' . $ligneCommande->getProduct()->getNom() . '</td>';
                            $messageEmail .= '<td style="border: 1px solid black">' . $ligneCommande->getProduct()->getConditionnement() . $ligneCommande->getProduct()->getUnit() .'</td>';
                            $messageEmail .= '<td style="border: 1px solid black">' . $ligneCommande->getProduct()->getPrixInit() . ' €' . '</td>';
                            $messageEmail .= '<td style="border: 1px solid black">' . $ligneCommande->getQuantite() . '</td>';
                            $messageEmail .= '</tr>';
                        }
                        $messageEmail .= '</table>';
    
                        $email = (new Email())
                            ->from($entityManager->getRepository(Settings::class)->findOneByName('contact_email')->getValue())
                            ->to($this->getUser()->getMail())
                            ->subject($translator->trans('email.subject.register_command'))
                            ->html($messageEmail);
    
                        $mailer->send($email);
    
                        return $response;
                    }
                    else {
                        $this->addFlash(
                            'warning',
                            $translator->trans('alert_message.limit_weight')
                        );
                        return $this->redirectToRoute('passe_commande_index');
                    }
                    
                }
                else {
                    $this->addFlash(
                        'warning',
                        $translator->trans('alert_message.limit_commande')
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
                $translator->trans('alert_message.closed_commande')
            );
            return $this->redirectToRoute('passe_commande_index');
        }

    }
    /**
     * @Route("/recap", name="recap", methods={"GET"})
     */
    public function recap(JourDistribRepository $jourDistribRepository): Response
    {
        $jourDistribs = $jourDistribRepository->findAll();
        $joursRecap=[];
        foreach ($jourDistribs as $jourDistrib) {
            array_push($joursRecap, ['jourDistrib' => $jourDistrib->getDate(), 'products' => $jourDistribRepository->recap($jourDistrib)]);
        }
        return $this->render('passe_commande/recap.html.twig', [
            'joursRecap' => $joursRecap,
        ]);
    }

    /**
     * @Route("/exportcsv/{id}", name="export_csv", methods={"GET"})
     */
    public function exportCsv(JourDistrib $jourDistrib, JourDistribRepository $jourDistribRepository): Response
    {
        $commandes = $jourDistribRepository->export($jourDistrib);

        $output = fopen("php://temp",'w') or die("Can't open php://output");
        fputcsv($output, array('id','nom','prenom', 'commentaire', 'produit', 'conditionnement', 'unite', 'quantitee', 'prix_initial', 'prix_final', 'livre', 'valid'));
        foreach($commandes as $commande) 
        {
            $commande['prixInit'] = number_format($commande['prixInit'], 2, ',', ' ');
            $commande['prixFinal'] = number_format($commande['prixFinal'], 2, ',', ' ');
            fputcsv($output, $commande);
        }
        rewind($output);
        
        $response = new Response(\stream_get_contents($output));
        fclose($output) or die("Can't close php://output");
        $response->headers->set('Content-Type', 'text/csv; charset=utf-8', 'application/force-download');
        $response->headers->set('Content-Disposition', 'attachment; filename="export_commandes.csv";');
        
        return $response;
    }

}