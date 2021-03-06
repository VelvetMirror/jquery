<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpFoundation\File;

use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\File\Exception\FileNotFoundException;

/**
 * A file uploaded through a form.
 *
 * @author Bernhard Schussek <bernhard.schussek@symfony.com>
 * @author Florian Eckerstorfer <florian@eckerstorfer.org>
 * @author Fabien Potencier <fabien@symfony.com>
 */
class UploadedFile extends File
{
    /**
     * The original name of the uploaded file.
     *
     * @var string
     */
    protected $originalName;

    /**
     * The mime type provided by the uploader.
     *
     * @var string
     */
    protected $mimeType;

    /**
     * The file size provided by the uploader.
     *
     * @var integer
     */
    protected $size;

    /**
     * The UPLOAD_ERR_XXX constant provided by the uploader.
     *
     * @var integer
     */
    protected $error;

    /**
     * Whether the uploaded file has already been moved.
     *
     * @var Boolean
     */
    protected $moved;

    /**
     * Accepts the information of the uploaded file as provided by the PHP global $_FILES.
     *
     * @param string  $path         The full temporary path to the file
     * @param string  $originalName The original file name
     * @param string  $mimeType     The type of the file as provided by PHP
     * @param integer $size         The file size
     * @param integer $error        The error constant of the upload (one of PHP's UPLOAD_ERR_XXX constants)
     * @param Boolean $moved        Whether the file has been moved from its original location
     *
     * @throws FileException         If file_uploads is disabled
     * @throws FileNotFoundException If the file does not exist
     */
    public function __construct($path, $originalName, $mimeType = null,
            $size = null, $error = null, $moved = false)
    {
        if (!ini_get('file_uploads')) {
            throw new FileException(sprintf('Unable to create UploadedFile because "file_uploads" is disabled in your php.ini file (%s)', get_cfg_var('cfg_file_path')));
        }

        if (!is_file($path)) {
            throw new FileNotFoundException($path);
        }

        $this->path = realpath($path);
        $this->originalName = basename($originalName);
        $this->mimeType = $mimeType ?: 'application/octet-stream';
        $this->size = $size;
        $this->error = $error ?: UPLOAD_ERR_OK;
        $this->moved = (Boolean) $moved;
    }

    /**
     * {@inheritDoc}
     */
    public function getMimeType()
    {
        return parent::getMimeType() ?: $this->mimeType;
    }

    /**
     * {@inheritDoc}
     */
    public function getSize()
    {
        return null === $this->size ? parent::getSize() : $this->size;
    }

    /**
     * {@inheritDoc}
     */
    public function getExtension()
    {
        if ($this->moved) {
            return parent::getExtension();
        }

        if ($ext = pathinfo($this->getOriginalName(), PATHINFO_EXTENSION)) {
            return '.'.$ext;
        }

        return '';
    }

    /**
     * Gets the original uploaded name.
     *
     * Warning: This name is not safe as it can have been manipulated by the end-user.
     * Moreover, it can contain characters that are not allowed in file names.
     * Never use it in a path.
     *
     * @return string
     */
    public function getOriginalName()
    {
        return $this->originalName;
    }

    /**
     * Returns the upload error.
     *
     * If the upload was successful, the constant UPLOAD_ERR_OK is returned.
     * Otherwise one of the other UPLOAD_ERR_XXX constants is returned.
     *
     * @return integer The upload error
     */
    public function getError()
    {
        return $this->error;
    }

    /**
     * Returns whether the file was uploaded successfully.
     *
     * @return Boolean  True if no error occurred during uploading
     */
    public function isValid()
    {
        return $this->error === UPLOAD_ERR_OK;
    }

    /**
     * {@inheritDoc}
     */
    public function move($directory, $name = null)
    {
        if ($this->moved) {
            return parent::move($directory, $name);
        }

        $newPath = $directory.DIRECTORY_SEPARATOR.(null === $name ? $this->getName() : $name);

        if (!@move_uploaded_file($this->getPath(), $newPath)) {
            $error = error_get_last();
            throw new FileException(sprintf('Could not move file %s to %s (%s)', $this->getPath(), $newPath, strip_tags($error['message'])));
        }

        $this->moved = true;
        $this->path = realpath($newPath);
    }
}
