# flat_fix_dl_filename

Drupal module that ensures that filenames are taken from the filename property of the file entity when downloading a file, rather than from the final segment of the flysystem URI, as that may differ for external content.