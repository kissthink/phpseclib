<?php
/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * Pure-PHP implementation of RC2.
 *
 * Uses mcrypt, if available, and an internal implementation, otherwise.
 *
 * PHP versions 4 and 5
 *
 * Useful resources are as follows:
 *
 *  - {@link @link http://tools.ietf.org/html/rfc2268}
 *
 * Here's a short example of how to use this library:
 * <code>
 * <?php
 *    include('Crypt/RC2.php');
 *
 *    $rc2 = new Crypt_RC2();
 *
 *    $rc2->setKey('abcdefgh');
 *
 *    $size = 10 * 1024;
 *    $plaintext = '';
 *    for ($i = 0; $i < $size; $i++) {
 *        $plaintext.= 'a';
 *    }
 *
 *    echo $rc2->decrypt($rc2->encrypt($plaintext));
 * ?>
 * </code>
 *
 * LICENSE: Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
 * THE SOFTWARE.
 *
 * @category   Crypt
 * @package    Crypt_RC2
 * @author     Patrick Monnerat <pm@datasphere.ch>
 * @license    http://www.opensource.org/licenses/mit-license.html  MIT License
 * @link       http://phpseclib.sourceforge.net
 */

/**
 * Include Crypt_Base
 *
 * Base cipher class
 */
if (!class_exists('Crypt_Base')) {
    require_once 'Base.php';
}

/**#@+
 * @access public
 * @see Crypt_RC2::encrypt()
 * @see Crypt_RC2::decrypt()
 */
/**
 * Define specific block modes for compatibility.
 */
define('CRYPT_RC2_MODE_CTR', CRYPT_MODE_CTR);
define('CRYPT_RC2_MODE_ECB', CRYPT_MODE_ECB);
define('CRYPT_RC2_MODE_CBC', CRYPT_MODE_CBC);
define('CRYPT_RC2_MODE_CFB', CRYPT_MODE_CFB);
define('CRYPT_RC2_MODE_OFB', CRYPT_MODE_OFB);
/**#@-*/

/**#@+
 * @access private
 * @see Crypt_RC2::Crypt_RC2()
 */
/**
 * Toggles the internal implementation
 */
define('CRYPT_RC2_MODE_INTERNAL', CRYPT_MODE_INTERNAL);
/**
 * Toggles the mcrypt implementation
 */
define('CRYPT_RC2_MODE_MCRYPT', CRYPT_MODE_MCRYPT);
/**#@-*/

/**
 * Pure-PHP implementation of RC2.
 *
 * @version 0.1.0
 * @access  public
 * @package Crypt_RC2
 */
class Crypt_RC2 extends Crypt_Base {
    /**
     * Block Length of the cipher
     *
     * @see Crypt_Base::block_size
     * @var Integer
     * @access private
     */
    var $block_size = 8;

    /**
     * The Key
     *
     * @see Crypt_Base::key
     * @see setKey()
     * @var String
     * @access private
     */
    var $key = "\0\0\0\0\0\0\0\0";

    /**
     * The default password key_size used by setPassword()
     *
     * @see Crypt_Base::password_key_size
     * @see Crypt_Base::setPassword()
     * @var Integer
     * @access private
     */
    var $password_key_size = 8;

    /**
     * The namespace used by the cipher for its constants.
     *
     * @see Crypt_Base::const_namespace
     * @var String
     * @access private
     */
    var $const_namespace = 'RC2';

    /**
     * The mcrypt specific name of the cipher
     *
     * @see Crypt_Base::cipher_name_mcrypt
     * @var String
     * @access private
     */
    var $cipher_name_mcrypt = 'rc2';

    /**
     * Optimizing value while CFB-encrypting
     *
     * @see Crypt_Base::cfb_init_len
     * @var Integer
     * @access private
     */
    var $cfb_init_len = 500;

    /**
     * max possible size of $key
     *
     * @see Crypt_RC2::setKey()
     * @var String
     * @access private
     */
    var $key_size_max = 128;

    /**
     * The Key Schedule
     *
     * @see Crypt_RC2::_setupKey()
     * @var Array
     * @access private
     */
    var $keys;

    /**
     * Key expansion randomization table and its inverse.
     *
     * @see Crypt_RC2::Crypt_RC2()
     * @see Crypt_RC2::setKey()
     * @var Array
     * @access private
     */
    var $pitable;
    var $invpitable;

    /**
     * Default Constructor.
     *
     * Determines whether or not the mcrypt extension should be used.
     *
     * $mode could be:
     *
     * - CRYPT_MODE_ECB
     *
     * - CRYPT_MODE_CBC
     *
     * - CRYPT_MODE_CTR
     *
     * - CRYPT_MODE_CFB
     *
     * - CRYPT_MODE_OFB
     *
     * If not explictly set, CRYPT_MODE_CBC will be used.
     *
     * @see Crypt_Base::Crypt_Base()
     * @param optional Integer $mode
     * @access public
     */
    function Crypt_RC2($mode = CRYPT_MODE_CBC)
    {
        parent::Crypt_Base($mode);
        $pitable = array(
            0xD9, 0x78, 0xF9, 0xC4, 0x19, 0xDD, 0xB5, 0xED,
            0x28, 0xE9, 0xFD, 0x79, 0x4A, 0xA0, 0xD8, 0x9D,
            0xC6, 0x7E, 0x37, 0x83, 0x2B, 0x76, 0x53, 0x8E,
            0x62, 0x4C, 0x64, 0x88, 0x44, 0x8B, 0xFB, 0xA2,
            0x17, 0x9A, 0x59, 0xF5, 0x87, 0xB3, 0x4F, 0x13,
            0x61, 0x45, 0x6D, 0x8D, 0x09, 0x81, 0x7D, 0x32,
            0xBD, 0x8F, 0x40, 0xEB, 0x86, 0xB7, 0x7B, 0x0B,
            0xF0, 0x95, 0x21, 0x22, 0x5C, 0x6B, 0x4E, 0x82,
            0x54, 0xD6, 0x65, 0x93, 0xCE, 0x60, 0xB2, 0x1C,
            0x73, 0x56, 0xC0, 0x14, 0xA7, 0x8C, 0xF1, 0xDC,
            0x12, 0x75, 0xCA, 0x1F, 0x3B, 0xBE, 0xE4, 0xD1,
            0x42, 0x3D, 0xD4, 0x30, 0xA3, 0x3C, 0xB6, 0x26,
            0x6F, 0xBF, 0x0E, 0xDA, 0x46, 0x69, 0x07, 0x57,
            0x27, 0xF2, 0x1D, 0x9B, 0xBC, 0x94, 0x43, 0x03,
            0xF8, 0x11, 0xC7, 0xF6, 0x90, 0xEF, 0x3E, 0xE7,
            0x06, 0xC3, 0xD5, 0x2F, 0xC8, 0x66, 0x1E, 0xD7,
            0x08, 0xE8, 0xEA, 0xDE, 0x80, 0x52, 0xEE, 0xF7,
            0x84, 0xAA, 0x72, 0xAC, 0x35, 0x4D, 0x6A, 0x2A,
            0x96, 0x1A, 0xD2, 0x71, 0x5A, 0x15, 0x49, 0x74,
            0x4B, 0x9F, 0xD0, 0x5E, 0x04, 0x18, 0xA4, 0xEC,
            0xC2, 0xE0, 0x41, 0x6E, 0x0F, 0x51, 0xCB, 0xCC,
            0x24, 0x91, 0xAF, 0x50, 0xA1, 0xF4, 0x70, 0x39,
            0x99, 0x7C, 0x3A, 0x85, 0x23, 0xB8, 0xB4, 0x7A,
            0xFC, 0x02, 0x36, 0x5B, 0x25, 0x55, 0x97, 0x31,
            0x2D, 0x5D, 0xFA, 0x98, 0xE3, 0x8A, 0x92, 0xAE,
            0x05, 0xDF, 0x29, 0x10, 0x67, 0x6C, 0xBA, 0xC9,
            0xD3, 0x00, 0xE6, 0xCF, 0xE1, 0x9E, 0xA8, 0x2C,
            0x63, 0x16, 0x01, 0x3F, 0x58, 0xE2, 0x89, 0xA9,
            0x0D, 0x38, 0x34, 0x1B, 0xAB, 0x33, 0xFF, 0xB0,
            0xBB, 0x48, 0x0C, 0x5F, 0xB9, 0xB1, 0xCD, 0x2E,
            0xC5, 0xF3, 0xDB, 0x47, 0xE5, 0xA5, 0x9C, 0x77,
            0x0A, 0xA6, 0x20, 0x68, 0xFE, 0x7F, 0xC1, 0xAD
        );
        $this->invpitable = array_flip($pitable);

        // Avoid a later modulus operation.
        $this->pitable = array_merge($pitable, $pitable);

        $this->setKey('');
        $this->setIV('');
    }

    /**
     * Sets the key.
     *
     * Keys can be of any length. RC2, itself, uses 1 to 1024 bit keys (eg.
     * strlen($key) <= 128), however, we only use the first 128 bytes if $key
     * has more then 128 bytes in it, and set $key to a single null byte if
     * it is empty.
     *
     * If the key is not explicitly set, it'll be assumed to be a single
     * null byte.
     *
     * @see Crypt_Base::setKey()
     * @access public
     * @param String $key
     * @param Integer $t1 optional          Effective key length in bits.
     */
    function setKey($key, $t1 = 1024)
    {
        // Key length should be 1..128.
        $key = strlen($key)? substr($key, 0, 128): "\x00";
        $t = strlen($key);

        // The mcrypt RC2 implementation only supports effective key length
        // of 1024 bits. It is however possible to handle effective key
        // lengths in range 1..1024 by expanding the key and applying
        // inverse pitable mapping to the first byte before submitting it
        // to mcrypt.

        // Key expansion.
        $l = array_values(unpack('C*', $key));
        $t8 = ($t1 + 7) >> 3;
        $tm = 0xFF >> (8 * $t8 - $t1);

        // Expand key.
        $pitable = $this->pitable;
        for ($i = $t; $i < 128; $i++) {
            $l[$i] = $pitable[$l[$i - 1] + $l[$i - $t]];
        }
        $i = 128 - $t8;
        $l[$i] = $pitable[$l[$i] & $tm];
        while ($i--) {
            $l[$i] = $pitable[$l[$i + 1] ^ $l[$i + $t8]];
        }

        // Prepare the key for mcrypt.
        $l[0] = $this->invpitable[$l[0]];
        array_unshift($l, 'C*');
        parent::setKey(call_user_func_array('pack', $l));
    }

    /**
     * Sets the initialization vector. (optional)
     *
     * SetIV is not required when CRYPT_MODE_ECB is being used.
     * If not explictly set, it'll be assumed to be all zero's.
     *
     * @access public
     * @param String $iv
     */
    function setIV($iv)
    {
        parent::setIV(str_pad(substr($iv, 0, 8), 8, "\x00"));
    }

    /**
     * Encrypts a block
     *
     * @see Crypt_Base::_encryptBlock()
     * @see Crypt_Base::encrypt()
     * @access private
     * @param String $in
     * @return String
     */
    function _encryptBlock($in)
    {
        list($r0, $r1, $r2, $r3) = array_values(unpack('v*', $in));
        $keys = $this->keys;
        $limit = 20;
        $actions = array($limit => 44, 44 => 64);
        $j = 0;

        for (;;) {
            // Mixing round.
            $r0 = (($r0 + $keys[$j++] + ((($r1 ^ $r2) & $r3) ^ $r1)) & 0xFFFF) << 1;
            $r0 |= $r0 >> 16;
            $r1 = (($r1 + $keys[$j++] + ((($r2 ^ $r3) & $r0) ^ $r2)) & 0xFFFF) << 2;
            $r1 |= $r1 >> 16;
            $r2 = (($r2 + $keys[$j++] + ((($r3 ^ $r0) & $r1) ^ $r3)) & 0xFFFF) << 3;
            $r2 |= $r2 >> 16;
            $r3 = (($r3 + $keys[$j++] + ((($r0 ^ $r1) & $r2) ^ $r0)) & 0xFFFF) << 5;
            $r3 |= $r3 >> 16;

            if ($j == $limit) {
                if ($limit == 64) {
                    break;
                }

                // Mashing round.
                $r0 += $keys[$r3 & 0x3F];
                $r1 += $keys[$r0 & 0x3F];
                $r2 += $keys[$r1 & 0x3F];
                $r3 += $keys[$r2 & 0x3F];
                $limit = $actions[$limit];
            }
        }

        return pack('vvvv', $r0, $r1, $r2, $r3);
    }

    /**
     * Decrypts a block
     *
     * @see Crypt_Base::_decryptBlock()
     * @see Crypt_Base::decrypt()
     * @access private
     * @param String $in
     * @return String
     */
    function _decryptBlock($in)
    {
        list($r0, $r1, $r2, $r3) = array_values(unpack('v*', $in));
        $keys = $this->keys;
        $limit = 44;
        $actions = array($limit => 20, 20 => 0);
        $j = 64;

        for (;;) {
            // R-mixing round.
            $r3 = ($r3 | ($r3 << 16)) >> 5;
            $r3 = ($r3 - $keys[--$j] - ((($r0 ^ $r1) & $r2) ^ $r0)) & 0xFFFF;
            $r2 = ($r2 | ($r2 << 16)) >> 3;
            $r2 = ($r2 - $keys[--$j] - ((($r3 ^ $r0) & $r1) ^ $r3)) & 0xFFFF;
            $r1 = ($r1 | ($r1 << 16)) >> 2;
            $r1 = ($r1 - $keys[--$j] - ((($r2 ^ $r3) & $r0) ^ $r2)) & 0xFFFF;
            $r0 = ($r0 | ($r0 << 16)) >> 1;
            $r0 = ($r0 - $keys[--$j] - ((($r1 ^ $r2) & $r3) ^ $r1)) & 0xFFFF;

            if ($j == $limit) {
                if (!$limit) {
                    break;
                }

                // R-mashing round.
                $r3 = ($r3 - $keys[$r2 & 0x3F]) & 0xFFFF;
                $r2 = ($r2 - $keys[$r1 & 0x3F]) & 0xFFFF;
                $r1 = ($r1 - $keys[$r0 & 0x3F]) & 0xFFFF;
                $r0 = ($r0 - $keys[$r3 & 0x3F]) & 0xFFFF;
                $limit = $actions[$limit];
            }
        }

        return pack('vvvv', $r0, $r1, $r2, $r3);
    }

    /**
     * Creates the key schedule
     *
     * @see Crypt_Base::_setupKey()
     * @access private
     */
    function _setupKey()
    {
        $l = unpack('Ca/Cb/v*', $this->key);
        $l[0] = $this->pitable[$l['a']] | ($l['b'] << 8);
        unset($l['a']);
        unset($l['b']);
        $this->keys = $l;
    }

    /**
     * Setup the performance-optimized function for de/encrypt()
     *
     * @see Crypt_Base::_setupInlineCrypt()
     * @access private
     */
    function _setupInlineCrypt()
    {
        $lambda_functions = &Crypt_RC2::_getLambdaFunctions();
        $code_hash = "Crypt_RC2, {$this->mode}";

        // Is there a re-usable $lambda_functions in there?
        // If not, we have to create it.
        if (!isset($lambda_functions[$code_hash])) {
            // Init code for both, encrypt and decrypt.
            $init_crypt = '
                list($r0, $r1, $r2, $r3) = array_values(unpack("v*", $text));
                $keys = $self->keys;';

            // Create code for encryption.
            $encrypt_block = '';

            $limit = 20;
            $actions = array($limit => 44, 44 => 64);
            $j = 0;

            for (;;) {
                // Mixing round.
                $encrypt_block .= '
                    $r0 = (($r0 + $keys[' . $j++ . '] +
                           ((($r1 ^ $r2) & $r3) ^ $r1)) & 0xFFFF) << 1;
                    $r0 |= $r0 >> 16;
                    $r1 = (($r1 + $keys[' . $j++ . '] +
                           ((($r2 ^ $r3) & $r0) ^ $r2)) & 0xFFFF) << 2;
                    $r1 |= $r1 >> 16;
                    $r2 = (($r2 + $keys[' . $j++ . '] +
                           ((($r3 ^ $r0) & $r1) ^ $r3)) & 0xFFFF) << 3;
                    $r2 |= $r2 >> 16;
                    $r3 = (($r3 + $keys[' . $j++ . '] +
                           ((($r0 ^ $r1) & $r2) ^ $r0)) & 0xFFFF) << 5;
                    $r3 |= $r3 >> 16;';

                if ($j == $limit) {
                    if ($limit == 64) {
                        break;
                    }

                    // Mashing round.
                    $encrypt_block .= '
                        $r0 += $keys[$r3 & 0x3F];
                        $r1 += $keys[$r0 & 0x3F];
                        $r2 += $keys[$r1 & 0x3F];
                        $r3 += $keys[$r2 & 0x3F];';
                    $limit = $actions[$limit];
                }
            }

            $encrypt_block .= '
                return pack("vvvv", $r0, $r1, $r2, $r3);';

            // Create code for decryption.
            $decrypt_block = '';
            $limit = 44;
            $actions = array($limit => 20, 20 => 0);
            $j = 64;

            for (;;) {
                // R-mixing round.
                $decrypt_block .= '
                    $r3 = ($r3 | ($r3 << 16)) >> 5;
                    $r3 = ($r3 - $keys[' . --$j . '] -
                           ((($r0 ^ $r1) & $r2) ^ $r0)) & 0xFFFF;
                    $r2 = ($r2 | ($r2 << 16)) >> 3;
                    $r2 = ($r2 - $keys[' . --$j . '] -
                           ((($r3 ^ $r0) & $r1) ^ $r3)) & 0xFFFF;
                    $r1 = ($r1 | ($r1 << 16)) >> 2;
                    $r1 = ($r1 - $keys[' . --$j . '] -
                           ((($r2 ^ $r3) & $r0) ^ $r2)) & 0xFFFF;
                    $r0 = ($r0 | ($r0 << 16)) >> 1;
                    $r0 = ($r0 - $keys[' . --$j . '] -
                           ((($r1 ^ $r2) & $r3) ^ $r1)) & 0xFFFF;';

                if ($j == $limit) {
                    if (!$limit) {
                        break;
                    }

                    // R-mashing round.
                    $decrypt_block .= '
                        $r3 = ($r3 - $keys[$r2 & 0x3F]) & 0xFFFF;
                        $r2 = ($r2 - $keys[$r1 & 0x3F]) & 0xFFFF;
                        $r1 = ($r1 - $keys[$r0 & 0x3F]) & 0xFFFF;
                        $r0 = ($r0 - $keys[$r3 & 0x3F]) & 0xFFFF;';
                    $limit = $actions[$limit];
                }
            }

            $decrypt_block .= '
                return pack("vvvv", $r0, $r1, $r2, $r3);';

            // Creates the inline-crypt function
            $lambda_functions[$code_hash] = $this->_createInlineCryptFunction(
                array(
                   'init_crypt'    => $init_crypt,
                   'encrypt_block' => $encrypt_block,
                   'decrypt_block' => $decrypt_block
                )
            );
        }

        // Set the inline-crypt function as callback in: $this->inline_crypt
        $this->inline_crypt = $lambda_functions[$code_hash];
    }
}

// vim: ts=4:sw=4:et:
// vim6: fdl=1:
