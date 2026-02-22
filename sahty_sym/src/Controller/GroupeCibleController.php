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
public function index(EntityManagerInterface $em): Response
{
    $groupes = $em->getRepository(GroupeCible::class)->findAll();

    return $this->render('groupe_cible/index.html.twig', [  
        'groupes' => $groupes,
    ]);
}
  #[Route('/new', name: 'groupe_cible_new', methods: ['GET', 'POST'])]
public function new(
    Request $request,
    EntityManagerInterface $em
): Response
{
    $groupe = new GroupeCible();
    
    // Get the referrer URL to know where to redirect back
    $referrer = $request->query->get('referrer');
    $eventId = $request->query->get('event_id');
    
    $form = $this->createForm(GroupeCibleType::class, $groupe);
    $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
        $em->persist($groupe);
        $em->flush();
        
        $this->addFlash('success', 'Groupe cible créé avec succès !');

        
        if ($referrer === 'evenements_evenement_add') {
            return $this->redirectToRoute('evenements_evenement_add');
        } elseif ($referrer === 'admin_evenement_update' && $eventId) {
            return $this->redirectToRoute('admin_evenement_update', ['id' => $eventId]);
        }
        
        return $this->redirectToRoute('groupe_cible_index');
    }

    return $this->render('groupe_cible/new.html.twig', [
        'form' => $form->createView(),
        'referrer' => $referrer,
        'event_id' => $eventId,
    ]);
}
    #[Route('/{id}/edit', name: 'groupe_cible_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, GroupeCible $groupe, EntityManagerInterface $em): Response
    {
        $form = $this->createForm(GroupeCibleType::class, $groupe);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();

            $this->addFlash('success', 'Groupe cible modifié avec succès !');

            return $this->redirectToRoute('groupe_cible_index');
        }

        return $this->render('groupe_cible/edit.html.twig', [
            'form' => $form->createView(),
            'groupe' => $groupe,
        ]);
    }

    #[Route('/{id}/delete', name: 'groupe_cible_delete', methods: ['POST'])]
    public function delete(Request $request, GroupeCible $groupe, EntityManagerInterface $em): Response
    {
        if ($this->isCsrfTokenValid('delete'.$groupe->getId(), $request->request->get('_token'))) {
            $em->remove($groupe);
            $em->flush();
            $this->addFlash('success', 'Groupe cible supprimé avec succès !');
        }

        return $this->redirectToRoute('groupe_cible_index');
    }
}
