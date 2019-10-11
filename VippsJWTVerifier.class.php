<?php /*
  This file is based on Firebases and fproject JWT.PHP (https://github.com/fproject/php-jwt/tree/master/src)  which is licensed like this:


Copyright (c) 2011, Neuman Vong

All rights reserved.

Redistribution and use in source and binary forms, with or without
modification, are permitted provided that the following conditions are met:

    * Redistributions of source code must retain the above copyright
      notice, this list of conditions and the following disclaimer.

    * Redistributions in binary form must reproduce the above
      copyright notice, this list of conditions and the following
      disclaimer in the documentation and/or other materials provided
      with the distribution.

    * Neither the name of Neuman Vong nor the names of other
      contributors may be used to endorse or promote products derived
      from this software without specific prior written permission.

THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS
"AS IS" AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT
LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR
A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT
OWNER OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL,
SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT
LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE,
DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY
THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
(INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE
OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.

    © 2019 GitHub, Inc.
    Terms
    Privacy
    Security
    Status
    Help

    Contact GitHub
    Pricing
    API
    Training
    Blog
    About
*/

/* This is an *extremely basic* JWT identifier, which only supports 'none' hashes and the RSA256 method, which is what Vipps is currently using. It causes a dependency on OpenSSL.
   It should in the future modified to use https://web-token.spomky-labs.com/' JWT Framework, which implements EdDSA and ECDH-ES. However, this will require php 7.2+  and several other
   dependencies as well as OpenSSL. This file can be extended to use phpseclib as a possible replacement for OpenSSL to ensure compatibility. IOK 2019-10-11
*/
class VippsJWTVerifier {
    public static $leeway = 0;
    public static $timestamp = null;

    public static $supported_algs = array(
        'HS256' => array('hash_hmac', 'SHA256'),
        'HS512' => array('hash_hmac', 'SHA512'),
        'HS384' => array('hash_hmac', 'SHA384'),
        'RS256' => array('verify_rsa', 'SHA256'),
        'RS384' => array('verify_rsa', 'SHA384'),
        'RS512' => array('verify_rsa', 'SHA512'),
    );

    public static function verify_idtoken($idtoken, $keys) {
       $timestamp = is_null(static::$timestamp) ? time() : static::$timestamp;

       @list($headb64,$bodyb64,$cryptob64) = explode('.', $idtoken);
       if (!$headb64 or !$bodyb64 or !$cryptob64) {
         return array('status'=>0, 'msg'=>'malformed_idtoken','data'=>null);
       }

       $headjson = static::base64urldecode($headb64);
       $bodyjson  = static::base64urldecode($bodyb64);
       $crypto = static::base64urldecode($cryptob64);

       $head = @json_decode($headjson, true, 512, JSON_BIGINT_AS_STRING);
       $body = @json_decode($bodyjson, true, 512, JSON_BIGINT_AS_STRING);

       if (!$head  || !$body) return array('status'=>0, 'msg'=>'malformed_head_or_body');

       if (empty($head['alg'])) return array('status'=>0,'msg'=>'empty_algorithm', 'data'=>null);
       if (empty(static::$supported_algs[$head['alg']])) return array('status'=>0,'msg'=>'unsupported_algorithm', 'data'=>null);

       $alg = $head['alg'];
       $kid = $head['kid'];
       $key = null;


       foreach ($keys  as $candidate) {
            if ($candidate['kid'] == $kid) {
              $key = $candidate; break;
            }
       }
       if (!$key || $key['alg'] != $alg) {
         return array('status'=>0, 'msg'=>'missing_or_wrong_key','data'=>null); 
       }  

       $result = static::verify("$headb64.$bodyb64", $crypto, $key, $alg);
print_r($result); 

       if (is_array($result)) return $result;
       if (!$result) {
          return array('status'=>0, 'msg'=>'not_verified','data'=>null);
       }
       if (isset($body['nbf']) && $body['nbf'] > ($timestamp + static::$leeway)) {
         return array('status'=>0, 'msg'=>'too_early','data'=>null);
       }
       if (isset($body['iat']) && $body['iat'] > ($timestamp + static::$leeway)) {
         return array('status'=>0, 'msg'=>'too_early','data'=>null);
       }
       if (isset($body['exp']) && ($timestamp - static::$leeway) >= $body['exp']) {
         return array('status'=>0,'msg'=>'expired','data'=>null);
       }
       return array('status'=>1, 'msg'=>'ok', 'data'=>$body);
    }


    public static function base64urldecode($input) {
        $remainder = strlen($input) % 4;
        if ($remainder) {
            $padlen = 4 - $remainder;
            $input .= str_repeat('=', $padlen);
        }
       return base64_decode(strtr($input, '-_', '+/'));
     }
     public static function encodeLength($length) {
        if ($length <= 0x7F) {
            return chr($length);
        }
        $temp = ltrim(pack('N', $length), chr(0));
        return pack('Ca*', 0x80 | strlen($temp), $temp);
    }


    public static function createPemFromModulusAndExponent($n, $e) {
        $modulus = static::base64urldecode($n);
        $publicExponent = static::base64urldecode($e);
        $components = array(
            'modulus' => pack('Ca*a*', 2, static::encodeLength(strlen($modulus)), $modulus),
            'publicExponent' => pack('Ca*a*', 2, static::encodeLength(strlen($publicExponent)), $publicExponent)
        );
        $RSAPublicKey = pack(
            'Ca*a*a*',
            48,
            static::encodeLength(strlen($components['modulus']) + strlen($components['publicExponent'])),
            $components['modulus'],
            $components['publicExponent']
        );
        // sequence(oid(1.2.840.113549.1.1.1), null)) = rsaEncryption.
        $rsaOID = pack('H*', '300d06092a864886f70d0101010500'); // hex version of MA0GCSqGSIb3DQEBAQUA
        $RSAPublicKey = chr(0) . $RSAPublicKey;
        $RSAPublicKey = chr(3) . static::encodeLength(strlen($RSAPublicKey)) . $RSAPublicKey;
        $RSAPublicKey = pack(
            'Ca*a*',
            48,
            static::encodeLength(strlen($rsaOID . $RSAPublicKey)),
            $rsaOID . $RSAPublicKey
        );
        $RSAPublicKey = "-----BEGIN PUBLIC KEY-----\r\n" .
            chunk_split(base64_encode($RSAPublicKey), 64) .
            '-----END PUBLIC KEY-----';
        return $RSAPublicKey;
    }

    public static function verify_rsa($message,$signature,$key,$algorithm) {
       if (!function_exists('openssl_verify')) {
          return array('status'=>0,'msg'=>'openssl_missing','data'=>null);
       }

       $pem = null;
       $n = $key['n'];
       $e = $key['e'];
       $pem=static::createPemFromModulusAndExponent($n,$e);
       if (!$pem) return array('status'=>0,'msg'=>'invalid_key','data'=>null);
//       $success = openssl_verify("$headb64.$bodyb64", $crypto, $pem, $algorithm);
       $success = openssl_verify($message, $signature, $pem, $algorithm);
       return $success;
    }


    private static function verify($msg, $signature, $key, $alg) {
        if (empty(static::$supported_algs[$alg])) return array('status'=>0,'msg'=>'unsupported_algorithm', 'data'=>null);

        list($function, $algorithm) = static::$supported_algs[$alg];
        switch($function) {
            case 'verify_rsa':
                $success = static::verify_rsa($msg, $signature, $key, $algorithm);
                if ($success === 1) {
                    return true;
                } elseif ($success === 0) {
                    return array('status'=>0,'msg'=>'not_verified', 'data'=>null);
                } else {
                    return array('status'=>0,'msg'=>'rsa_error', 'data'=>null);
                }
            case 'hash_hmac':
            default:
                $hash = hash_hmac($algorithm, $msg, $key, true);
                if (function_exists('hash_equals')) {
                    return hash_equals($signature, $hash);
                }
                $len = min(static::strlen($signature), static::strlen($hash));
                $status = 0;
                for ($i = 0; $i < $len; $i++) {
                    $status |= (ord($signature[$i]) ^ ord($hash[$i]));
                }
                $status |= (static::safeStrlen($signature) ^ static::safeStrlen($hash));
                return ($status === 0);
        }
    }


                                                                          
}
