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
use Drupal\file\Entity\File;
use Drupal\file_entity\Entity\FileEntity;

class RouteSubscriber implements EventSubscriberInterface {

  protected EntityTypeManagerInterface $entityTypeManager;

  public function __construct(EntityTypeManagerInterface $entityTypeManager) {
    $this->entityTypeManager = $entityTypeManager;
  }

  public static function getSubscribedEvents(): array {
    return [
      'kernel.response' => ['onKernelResponse', -10],
    ];
  }

  public function onKernelResponse(ResponseEvent $event): void {
    $request = $event->getRequest();
    $route_name = $request->attributes->get('_route');
    $response = $event->getResponse();

    if (!$response instanceof Response) {
      return;
    }

    // Determine the appropriate filename based on the route.
    $filename = match ($route_name) {
      'file_entity.file_download' => $this->getFilenameFromFileEntity($request->attributes->get('file')),
      'flysystem.files', 'flysystem.serve' => $this->getFilenameFromFlysystem($request, $route_name),
      default => NULL,
    };

    if ($filename) {
      $sanitized = preg_replace('/[^A-Za-z0-9.\-_]/', '_', $filename);
      $disposition = $response->headers->makeDisposition(
        ResponseHeaderBag::DISPOSITION_ATTACHMENT,
        $sanitized
      );
      $response->headers->set('Content-Disposition', $disposition);
    }
  }

  private function getFilenameFromFileEntity($file_entity): ?string {
    if ($file_entity instanceof FileEntity) {
      return $file_entity->getFilename();
    }
    \Drupal::logger('flat_fix_dl_filename')->warning('File entity not found in request attributes.');
    return NULL;
  }

  private function getFilenameFromFlysystem($request, string $route_name): ?string {
    $scheme = $request->attributes->get('scheme');
    $filepath = $request->attributes->get('filepath');

    if (empty($filepath)) {
      $path_info = $request->getPathInfo();
      $prefix = "/_flysystem/{$scheme}/";
      if (str_starts_with($path_info, $prefix)) {
        $filepath = substr($path_info, strlen($prefix));
      }
    }

    if (empty($scheme) || empty($filepath)) {
      \Drupal::logger('flat_fix_dl_filename')->warning("Missing scheme or filepath for route $route_name.");
      return NULL;
    }

    $full_uri = "{$scheme}://" . urldecode($filepath);

    $files = $this->entityTypeManager->getStorage('file')->loadByProperties(['uri' => $full_uri]);
    $file = $files ? reset($files) : NULL;

    if ($file instanceof File) {
      return $file->getFilename();
    }

    \Drupal::logger('flat_fix_dl_filename')->warning("No file entity found for URI: $full_uri");
    return NULL;
  }
}
