<?php

namespace Devlabs\SportifyBundle\Controller;

use Devlabs\SportifyBundle\Entity\Score;
use Devlabs\SportifyBundle\Entity\Prediction;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;

/**
 * Class MatchesController
 * @package Devlabs\SportifyBundle\Controller
 */
class MatchesController extends Controller
{
    /**
     * @Route("/matches/{tournament}/{date_from}/{date_to}",
     *     name="matches_index",
     *     defaults={"tournament" = "all", "date_from" = "2016-01-01", "date_to" = "2021-12-31"})
     */
    public function indexAction(Request $request, $tournament, $date_from, $date_to)
    {
        // Load the data for the current user into an object
        $user = $this->getUser();

        // Get an instance of the Entity Manager
        $em = $this->getDoctrine()->getManager();

        // use the selected tournament as object, based on id URL: {tournament} route parameter
        $tournamentSelected = ($tournament === 'all')
            ? null
            : $em->getRepository('DevlabsSportifyBundle:Tournament')->findOneById($tournament);

        // get joined tournaments
        $tournamentsJoined = $em->getRepository('DevlabsSportifyBundle:Tournament')
            ->getJoined($user);

        // creating a form for the tournament and date filter
        $formData = array();
        $filterForm = $this->createFormBuilder($formData)
            ->setAction($this->generateUrl('matches_index'))
            ->add('tournament_id', EntityType::class, array(
                'class' => 'DevlabsSportifyBundle:Tournament',
                'choices' => $tournamentsJoined,
                'choice_label' => 'name',
                'label' => false,
                'data' => $tournamentSelected
            ))
            ->add('date_from', DateType::class, array(
                'input' => 'string',
                'format' => 'yyyy-MM-dd',
//                'widget' => 'single_text',
                'label' => false,
                'years' => range(date('Y') -10, date('Y') +10),
                'data' => $date_from
            ))
            ->add('date_to', DateType::class, array(
                'input' => 'string',
                'format' => 'yyyy-MM-dd',
//                'widget' => 'single_text',
                'label' => false,
                'years' => range(date('Y') -10, date('Y') +10),
                'data' => $date_to
            ))
            ->add('button', SubmitType::class, array('label' => 'Filter'))
            ->getForm();

        $filterForm->handleRequest($request);

        // if the filter form is submitted, redirect with appropriate url path parameters
        if ($filterForm->isSubmitted() && $filterForm->isValid()) {
            $formData = $filterForm->getData();

            $tournamentChoice = $formData['tournament_id'];
            $dateFromChoice = $formData['date_from'];
            $dateToChoice = $formData['date_to'];

            // reload the page with the chosen filter(s) applied (as url path params)
            return $this->redirectToRoute(
                'matches_index',
                array(
                    'tournament' => $tournamentChoice->getId(),
                    'date_from' => $dateFromChoice,
                    'date_to' => $dateToChoice
                )
            );
        }

        // get not finished matches and the user's predictions for them
        $matches = $em->getRepository('DevlabsSportifyBundle:Match')
            ->getNotScored($user, $tournament, $date_from, $date_to);
        $predictions = $em->getRepository('DevlabsSportifyBundle:Prediction')
            ->getNotScored($user, $tournament, $date_from, $date_to);

        $matchForms = array();

        if ($matches) {
            // creating a form with BET/EDIT button for each match
            foreach ($matches as $match) {
                $prediction = new Prediction();
                $prediction->setHomeGoals('');
                $prediction->setAwayGoals('');
                $buttonAction = 'bet';

                if (isset($predictions[$match->getId()])) {
                    $prediction = $predictions[$match->getId()];
                    $buttonAction = 'edit';
                }

                $formData = array();
                $form = $this->createFormBuilder($formData)
                    ->add('match_id', HiddenType::class, array('data' => $match->getId()))
                    ->add('home_goals', TextType::class, array('data' => $prediction->getHomeGoals()))
                    ->add('away_goals', TextType::class, array('data' => $prediction->getAwayGoals()))
                    ->add('action', HiddenType::class, array('data' => $buttonAction))
                    ->add('button', SubmitType::class, array('label' => $buttonAction))
                    ->getForm();

                $form->handleRequest($request);
                $matchForms[$match->getId()] = $form;
            }

            // iterate the forms and and if form is submitted, then execute the bed/edit prediction code
            foreach ($matchForms as $form) {
                if ($form->isSubmitted() && $form->isValid()) {
                    $formData = $form->getData();
                    $match = $em->getRepository('DevlabsSportifyBundle:Match')
                        ->findOneById($formData['match_id']);

                    // prepare the Prediction object (new or modified one) for persisting in DB
                    if ($formData['action'] === 'bet') {
                        $prediction = new Prediction();
                        $prediction->setUserId($user);
                        $prediction->setMatchId($match);
                        $prediction->setHomeGoals($formData['home_goals']);
                        $prediction->setAwayGoals($formData['away_goals']);
                    } elseif ($formData['action'] === 'edit') {
                        $prediction = $em->getRepository('DevlabsSportifyBundle:Prediction')
                            ->getOneByUserAndMatch($user, $match);
                        $prediction->setHomeGoals($formData['home_goals']);
                        $prediction->setAwayGoals($formData['away_goals']);
                    }

                    // prepare the queries
                    $em->persist($prediction);

                    // execute the queries
                    $em->flush();

                    // clear the submitted POST data and reload the page
                    return $this->redirectToRoute(
                        'matches_index',
                        array(
                            'tournament' => $tournament,
                            'date_from' => $date_from,
                            'date_to' => $date_to
                        )
                    );
                }
            }

            // create view for each form
            foreach ($matchForms as &$form) {
                $form = $form->createView();
            }
        }


        // rendering the view and returning the response
        return $this->render(
            'DevlabsSportifyBundle:Matches:index.html.twig',
            array(
                'matches' => $matches,
                'predictions' => $predictions,
                'filter_form' => $filterForm->createView(),
                'match_forms' => $matchForms
            )
        );
    }
}
