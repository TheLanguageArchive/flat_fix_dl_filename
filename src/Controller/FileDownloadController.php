<?php

namespace Drupal\flat_fix_dl_filename\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Drupal\file\FileInterface;

class FileDownloadController extends ControllerBase
{

  public function downloadFromFlysystem($path, Request $request)
  {
    $file = $this->getFileFromFlysystemPath($path);

    if (!$file instanceof FileInterface) {
      throw new NotFoundHttpException();
    }

    if (!$file->access('download')) {
      throw new AccessDeniedHttpException();
    }

    $filename = preg_replace('/[^A-Za-z0-9.\-_]/', '_', $file->getFilename());

    $uri = $file->getFileUri();
    $real_path = \Drupal::service('file_system')->realpath($uri);
    if (!$real_path || !file_exists($real_path)) {
      throw new NotFoundHttpException();
    }

    $response = new BinaryFileResponse($real_path);
    $disposition = $response->headers->makeDisposition(
      ResponseHeaderBag::DISPOSITION_ATTACHMENT,
      $filename
    );
    $response->headers->set('Content-Disposition', $disposition);

    return $response;
  }

  protected function getFileFromFlysystemPath($path)
  {
    $files = \Drupal::entityTypeManager()->getStorage('file')->loadByProperties([
      'uri' => 'fedora://' . $path,
    ]);
    return $files ? reset($files) : NULL;
  }
}
