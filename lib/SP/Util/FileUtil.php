<?php
/**
 * sysPass
 *
 * @author    nuxsmin
 * @link      https://syspass.org
 * @copyright 2012-2018, Rubén Domínguez nuxsmin@$syspass.org
 *
 * This file is part of sysPass.
 *
 * sysPass is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * sysPass is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 *  along with sysPass.  If not, see <http://www.gnu.org/licenses/>.
 */

namespace SP\Util;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SP\DataModel\FileData;

/**
 * Class FileUtil
 *
 * @package SP\Util
 */
class FileUtil
{
    /**
     * @var array
     */
    public static $imageExtensions = ['JPG', 'PNG', 'GIF'];

    /**
     * Removes a directory in a recursive way
     *
     * @param $dir
     * @return bool
     * @see https://stackoverflow.com/a/7288067
     */
    public static function rmdir_recursive($dir)
    {
        if (!is_dir($dir)) {
            return true;
        }

        $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);

        foreach ($it as $file) {
            if ($file->isDir()) rmdir($file->getPathname());
            else unlink($file->getPathname());
        }

        return rmdir($dir);
    }

    /**
     * @param FileData $FileData
     * @return bool
     */
    public static function isImage(FileData $FileData)
    {
        return in_array(mb_strtoupper($FileData->getExtension()), self::$imageExtensions, true);
    }
}