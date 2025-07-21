<?php

namespace Drupal\flat_fix_dl_filename\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Request;

class FileDownloadController extends ControllerBase {

  public function downloadFromFlysystem($path, Request $request) {

    //\Drupal::logger('flat_fix_dl_filename')->notice('downloadFromFlysystem triggerd: ' . $path);

    // Map the Flysystem path to the corresponding file in Fedora or wherever the file resides.
    $file = $this->getFileFromFlysystemPath($path);

    if (!$file) {
      // Return a 404 if no file found.
      throw new \Symfony\Component\HttpKernel\Exception\NotFoundHttpException();
    }

    // Get the filename from the File Entity.
    $filename = $file->getFilename();

    // Sanitize the filename.
    $sanitized_filename = preg_replace('/[^A-Za-z0-9.\-_]/', '_', $filename);

    // Return the response to allow downloading.
    $response = new BinaryFileResponse($file->getFileUri());
    $disposition = $response->headers->makeDisposition(ResponseHeaderBag::DISPOSITION_ATTACHMENT, $sanitized_filename);
    $response->headers->set('Content-Disposition', $disposition);

    return $response;
  }

  /**
   * Get file from Flysystem path (map it to the correct file).
   */
  protected function getFileFromFlysystemPath($path) {
    // Map the Flysystem path to a file entity or whatever storage you are using.
    // Example logic:
    $file = \Drupal::entityTypeManager()->getStorage('file')->loadByProperties(['uri' => 'fedora://' . $path]);

    return $file ? reset($file) : NULL;
  }
}
