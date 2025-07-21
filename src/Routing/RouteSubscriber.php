<?php

/**
 * @file
 * Contains \Drupal\flat_deposit\Routing\RouteSubscriber.
 *
 * Taking the filename property from the file entity and modifying the response headers, to avoid Drupal using the
 * final path component of the (streamwrapped) Fedora file URI as the filename when serving files.
 */

namespace Drupal\flat_fix_dl_filename\Routing;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Drupal\Core\Entity\EntityTypeManagerInterface;

class RouteSubscriber implements EventSubscriberInterface {

  protected $entityTypeManager;

  public function __construct(EntityTypeManagerInterface $entity_type_manager) {
    $this->entityTypeManager = $entity_type_manager;
  }

  public static function getSubscribedEvents() {
    return [
      'kernel.response' => ['onKernelResponse', -10],
    ];
  }

  public function onKernelResponse(ResponseEvent $event) {

    $request = $event->getRequest();
    $route_name = $request->attributes->get('_route');


    if ($route_name === 'file_entity.file_download') {

      // Get the file entity directly from the request attributes
      $file_entity = $request->attributes->get('file');

      if ($file_entity instanceof \Drupal\file_entity\Entity\FileEntity) {
        $original_filename = $file_entity->getFilename();

        // Sanitize filename: Remove special characters like % and spaces
        $sanitized_filename = preg_replace('/[^A-Za-z0-9.\-_]/', '_', $original_filename);

        // Modify the response headers.
        $response = $event->getResponse();
        if ($response instanceof Response) {
          $disposition = $response->headers->makeDisposition(
            ResponseHeaderBag::DISPOSITION_ATTACHMENT,
            $sanitized_filename
          );
          $response->headers->set('Content-Disposition', $disposition);
        }
      } else {
        \Drupal::logger('flat_fix_dl_filename')->warning('File entity not found in request attributes.');
      }
    }
  }
}
