<?php

declare(strict_types=1);

namespace App\Shared\Exception;

/**
 * Un utilisateur authentifié n'a strictement aucun Membership (docs/07-data-model.md, section
 * 5 : un User a au moins un Membership dès la fin de l'inscription - Phase 2 - et peut en
 * acquérir d'autres via une invitation acceptée - Phase 14, DEC-009). Zéro Membership reste
 * donc une violation d'invariant interne, jamais un état atteignable par un chemin
 * applicatif normal : traité comme une erreur 500 catégorisée
 * (App\Shared\Http\ApiExceptionListener), jamais comme un 401/403, et ne doit jamais laisser
 * passer une requête Doctrine tenant-scoped sans filtre actif.
 *
 * À distinguer de App\Shared\Exception\OrganizationMembershipMismatchException : un
 * utilisateur qui a des Membership mais dont aucun ne correspond au claim `org` du JWT
 * présenté (ex. retiré de l'organisation après l'émission du jeton) n'est jamais cette
 * exception-ci - c'est un état atteignable en fonctionnement normal, traité comme un échec
 * d'authentification ordinaire (401), pas comme un bug interne.
 */
final class AuthenticatedIdentityWithoutOrganizationException extends \RuntimeException
{
}
