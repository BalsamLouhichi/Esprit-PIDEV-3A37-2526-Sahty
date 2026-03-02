<?php

namespace App\Controller;

use App\Entity\GroupeCible;
use App\Form\GroupeCibleType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/groupe-cible')]
final class GroupeCibleController extends AbstractController
{
    #[Route('/', name: 'groupe_cible_index', methods: ['GET'])]
    public function index(Request $request, EntityManagerInterface $em): Response
    {
        $groupes = $em->getRepository(GroupeCible::class)->findAll();
        $referrer = (string) $request->query->get('referrer', '');
        $eventId = $request->query->get('event_id');

        return $this->render('groupe_cible/index.html.twig', [
            'groupes' => $groupes,
            'referrer' => $referrer,
            'event_id' => $eventId,
        ]);
    }

    #[Route('/new', name: 'groupe_cible_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $em): Response
    {
        $groupe = new GroupeCible();
        $referrer = (string) $request->query->get('referrer', '');
        $eventId = $request->query->get('event_id');
        $returnTo = (string) $request->query->get('return_to', 'index');

        $form = $this->createForm(GroupeCibleType::class, $groupe);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // If client creates a group without type, default to their role keyword
            // so it appears in their filtered event form list.
            $submittedType = trim((string) $groupe->getType());
            if ($submittedType === '') {
                $defaultType = $this->defaultGroupeTypeFromCurrentUserRole();
                if ($defaultType !== null) {
                    $groupe->setType($defaultType);
                }
            }

            $em->persist($groupe);
            $em->flush();

            $this->addFlash('success', 'Groupe cible cree avec succes.');

            if ($referrer === 'evenements_client_demande_evenement' && $returnTo === 'form') {
                return $this->redirectToRoute('evenements_client_demande_evenement');
            }

            return $this->redirectToRoute(...$this->resolveBackRoute($referrer, $eventId, true));
        }

        return $this->render('groupe_cible/new.html.twig', [
            'form' => $form->createView(),
            'referrer' => $referrer,
            'event_id' => $eventId,
        ]);
    }

    private function defaultGroupeTypeFromCurrentUserRole(): ?string
    {
        if ($this->isGranted('ROLE_PATIENT')) {
            return 'patient';
        }
        if ($this->isGranted('ROLE_MEDECIN')) {
            return 'medecin';
        }
        if ($this->isGranted('ROLE_RESPONSABLE_LABO')) {
            return 'laboratoire';
        }
        if ($this->isGranted('ROLE_RESPONSABLE_PARA')) {
            return 'paramedical';
        }

        return null;
    }

    #[Route('/{id}/edit', name: 'groupe_cible_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, GroupeCible $groupe, EntityManagerInterface $em): Response
    {
        $referrer = (string) $request->query->get('referrer', '');
        $eventId = $request->query->get('event_id');

        $form = $this->createForm(GroupeCibleType::class, $groupe);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();
            $this->addFlash('success', 'Groupe cible modifie avec succes.');

            return $this->redirectToRoute(...$this->resolveBackRoute($referrer, $eventId, true));
        }

        return $this->render('groupe_cible/edit.html.twig', [
            'form' => $form->createView(),
            'groupe' => $groupe,
            'referrer' => $referrer,
            'event_id' => $eventId,
        ]);
    }

    #[Route('/{id}/delete', name: 'groupe_cible_delete', methods: ['POST'])]
    public function delete(Request $request, GroupeCible $groupe, EntityManagerInterface $em): Response
    {
        $referrer = (string) $request->query->get('referrer', '');
        $eventId = $request->query->get('event_id');

        if ($this->isCsrfTokenValid('delete'.$groupe->getId(), (string) $request->request->get('_token'))) {
            $em->remove($groupe);
            $em->flush();
            $this->addFlash('success', 'Groupe cible supprime avec succes.');
        }

        return $this->redirectToRoute(...$this->resolveBackRoute($referrer, $eventId, true));
    }

    /**
     * @return array{0:string,1:array<string,mixed>}
     */
    private function resolveBackRoute(string $referrer, mixed $eventId, bool $toIndex = false): array
    {
        if ($referrer === 'evenements_client_demande_evenement') {
            if ($toIndex) {
                return ['groupe_cible_index', ['referrer' => 'evenements_client_demande_evenement']];
            }

            return ['evenements_client_demande_evenement', []];
        }

        if ($referrer === 'evenements_evenement_add') {
            if ($toIndex) {
                return ['groupe_cible_index', ['referrer' => 'evenements_evenement_add']];
            }

            return ['evenements_evenement_add', []];
        }

        if ($referrer === 'admin_evenement_update' && $eventId) {
            if ($toIndex) {
                return ['groupe_cible_index', [
                    'referrer' => 'admin_evenement_update',
                    'event_id' => $eventId,
                ]];
            }

            return ['admin_evenement_update', ['id' => $eventId]];
        }

        return ['groupe_cible_index', []];
    }
}
