<?php

namespace App\Controller;

use App\Entity\Evenement;
use App\Entity\InscriptionEvenement;
use App\Form\InscriptionEvenementType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/inscription/evenement')]
final class InscriptionEvenementController extends AbstractController
{
    #[Route('/nouveau/{evenementId}', name: 'app_inscription_evenement_new', methods: ['GET', 'POST'])]
    public function nouveau(
        Request $request,
        EntityManagerInterface $entityManager,
        int $evenementId
    ): Response {
        $evenement = $entityManager->getRepository(Evenement::class)->find($evenementId);

        if (!$evenement) {
            throw $this->createNotFoundException('Evenement non trouve');
        }

        if ($this->getUser()) {
            $existingInscription = $entityManager->getRepository(InscriptionEvenement::class)
                ->findOneBy([
                    'evenement' => $evenement,
                    'utilisateur' => $this->getUser(),
                ]);

            if ($existingInscription) {
                $this->addFlash('warning', 'Vous etes deja inscrit a cet evenement.');
                return $this->redirectToRoute('app_evenement_show', ['id' => $evenementId]);
            }
        }

        if ($evenement->getPlacesMax() !== null) {
            $inscriptionsCount = $entityManager->getRepository(InscriptionEvenement::class)
                ->count(['evenement' => $evenement, 'statut' => ['confirme', 'paye']]);

            if ($inscriptionsCount >= $evenement->getPlacesMax()) {
                $this->addFlash('error', 'Desole, cet evenement est complet.');
                return $this->redirectToRoute('app_evenement_show', ['id' => $evenementId]);
            }
        }

        $inscriptionEvenement = new InscriptionEvenement();
        $inscriptionEvenement->setEvenement($evenement);
        $inscriptionEvenement->setDateInscription(new \DateTime());
        $inscriptionEvenement->setCreeLe(new \DateTime());
        $inscriptionEvenement->setStatut('en_attente');
        $inscriptionEvenement->setPresent(false);

        if ($this->getUser()) {
            $inscriptionEvenement->setUtilisateur($this->getUser());
        }

        $form = $this->createForm(InscriptionEvenementType::class, $inscriptionEvenement, [
            'evenement' => $evenement,
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $entityManager->persist($inscriptionEvenement);
                $entityManager->flush();

                $reference = 'INS-' . date('Y') . '-' . str_pad((string) $inscriptionEvenement->getId(), 5, '0', STR_PAD_LEFT);
                $inscriptionEvenement->setReference($reference);
                $entityManager->flush();

                $this->addFlash('success', 'Inscription enregistree avec succes !');

                return $this->redirectToRoute('app_inscription_evenement_confirmation', [
                    'id' => $inscriptionEvenement->getId(),
                ]);
            } catch (\Exception $e) {
                $this->addFlash('error', 'Une erreur est survenue lors de l\'inscription.');
            }
        }

        return $this->render('inscription_evenement/nouveau.html.twig', [
            'inscription_evenement' => $inscriptionEvenement,
            'evenement' => $evenement,
            'form' => $form,
        ]);
    }

    #[Route('/confirmation/{id}', name: 'app_inscription_evenement_confirmation', methods: ['GET'])]
    public function confirmation(InscriptionEvenement $inscriptionEvenement): Response
    {
        return $this->render('inscription_evenement/confirmation.html.twig', [
            'inscription' => $inscriptionEvenement,
        ]);
    }

    #[Route('/mes-inscriptions', name: 'app_inscription_evenement_mes_inscriptions', methods: ['GET'])]
    public function mesInscriptions(EntityManagerInterface $entityManager): Response
    {
        $user = $this->getUser();

        if (!$user) {
            return $this->redirectToRoute('app_login');
        }

        $inscriptions = $entityManager->getRepository(InscriptionEvenement::class)
            ->findBy(['utilisateur' => $user], ['dateInscription' => 'DESC']);

        return $this->render('evenement/mes_inscriptions.html.twig', [
            'inscriptions' => $inscriptions,
        ]);
    }

    #[Route('/{id}/annuler', name: 'app_inscription_evenement_annuler', methods: ['POST'])]
    public function annuler(
        Request $request,
        InscriptionEvenement $inscriptionEvenement,
        EntityManagerInterface $entityManager
    ): Response {
        $token = (string) (string) $request->request->get('_token');
        if ($this->isCsrfTokenValid('annuler' . $inscriptionEvenement->getId(), is_string($token) ? $token : '')) {
            $inscriptionEvenement->setStatut('annule');
            $inscriptionEvenement->setModifieLe(new \DateTime());
            $entityManager->flush();

            $this->addFlash('success', 'Inscription annulee avec succes.');
        }

        return $this->redirectToRoute('app_inscription_evenement_mes_inscriptions');
    }

    #[Route('/{id}/payer', name: 'app_inscription_evenement_payer', methods: ['GET', 'POST'])]
    public function payer(
        Request $request,
        InscriptionEvenement $inscriptionEvenement,
        EntityManagerInterface $entityManager
    ): Response {
        $form = $this->createFormBuilder()
            ->add('paymentMethod', ChoiceType::class, [
                'label' => 'Methode de paiement',
                'choices' => [
                    'Carte bancaire' => 'card',
                    'PayPal' => 'paypal',
                ],
                'expanded' => true,
                'multiple' => false,
            ])
            ->getForm();

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $inscriptionEvenement->setStatut('paye');
            $inscriptionEvenement->setModifieLe(new \DateTime());
            $entityManager->flush();

            $this->addFlash('success', 'Paiement effectue avec succes !');
            return $this->redirectToRoute('app_inscription_evenement_confirmation', [
                'id' => $inscriptionEvenement->getId(),
            ]);
        }

        return $this->render('inscription_evenement/payer.html.twig', [
            'inscription' => $inscriptionEvenement,
            'form' => $form,
        ]);
    }

    #[Route('/api/calculer-prix', name: 'app_api_calculer_prix', methods: ['POST'])]
    public function calculerPrix(Request $request, EntityManagerInterface $entityManager): JsonResponse
    {
        $decoded = json_decode($request->getContent(), true);
        $data = is_array($decoded) ? $decoded : [];

        $userTypeRaw = $data['userType'] ?? 'visiteur';
        $userType = is_scalar($userTypeRaw) ? (string) $userTypeRaw : 'visiteur';

        $evenementIdRaw = $data['evenementId'] ?? null;
        $evenementId = is_scalar($evenementIdRaw) ? (int) $evenementIdRaw : null;

        $tarifBase = 25;

        if ($evenementId) {
            $evenement = $entityManager->getRepository(Evenement::class)->find($evenementId);
            if ($evenement && $evenement->getTarif()) {
                $tarifBase = (float) $evenement->getTarif();
            }
        }

        $reductions = [
            'patient' => 5,
            'medecin' => 0,
            'pharmacien' => 0,
            'laboratoire' => 0,
            'visiteur' => 10,
        ];

        $reduction = $reductions[$userType] ?? 0;
        $prixFinal = max(0, $tarifBase - $reduction);

        return $this->json([
            'success' => true,
            'prix' => $prixFinal,
            'tarifBase' => $tarifBase,
            'reduction' => $reduction,
            'devise' => 'EUR',
        ]);
    }
}





