<?php 
/*
      VippsSession:

        This is a version of this class that works with PHP 7.4

        This class implements persistent sessions stored in the database
        with a timeout value. It implements the ArrayAccess interface
        so that you can access the stored values as if it was a hash
        table. You can do this also after the session is 'destroyed'.

        The reason these sessions are needed is that during the WP login
        process, you may be redirected multiple times due to MFA plugins,
        and in this application, to confirmation screens.

        Basic usage: 
            VippsSession::create($data, $expiretime); => returns a  new Vipps Session stored in the database with a fresh key.
            $session->sessionkey = The key used to store the session, you can retrieve the session using this key and the method get()
            VippsSession::get($sessionkey) = returns a session given a key.
            $session->destroy()  = Delete a session from the database (but let it live on as an array)
            VipssSession::clean() = Delete all old sessions



            This file is part of the plugin Login with Vipps
            Copyright (c) 2019 WP-Hosting AS

            MIT License

            Copyright (c) 2019 WP-Hosting AS

            Permission is hereby granted, free of charge, to any person obtaining a copy
            of this software and associated documentation files (the "Software"), to deal
            in the Software without restriction, including without limitation the rights
            to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
            copies of the Software, and to permit persons to whom the Software is
            furnished to do so, subject to the following conditions:

            The above copyright notice and this permission notice shall be included in all
            copies or substantial portions of the Software.

            THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
            IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
            FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
            AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
            LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
            OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
            SOFTWARE.

 */

class VippsSession implements ArrayAccess {
    public $sessionkey = null;
    public $contents = null;
    // We allow read-only access to a destroyed session so that it acts exactly like an array. Except don't try to store it as one.
    protected $destroyed = false;

    public function __construct($sessionkey,$data=array()) {
        $this->sessionkey=$sessionkey;
        $this->contents = $data;
        return $this;
    }

    public function offsetExists ($offset) : bool {
        return isset($this->contents[$offset]);
    }
    public function offsetGet ($offset) {
        return $this->contents[$offset];
    }
    public function offsetSet($offset,$value) : void {
        $this->contents[$offset] = $value;
        $this->update($this->contents);
        return;
    }
    public function offsetUnset($offset) : void {
        unset($this->contents[$offset]);
        $this->update($this->contents);
        return;
    }
    public static function create($input=array(), $expire=3600) {
        global $wpdb;
        // Default expire is 3600
        if (!$expire || !is_int($expire)) $expire = 3600;
        $expiretime = gmdate('Y-m-d H:i:s', time() + $expire);

        $randombytes = random_bytes(256);
        $hash = hash('sha256',$randombytes,true);
        $sessionkey = base64_encode($hash);
        $ok = false;
        $count = 0;
        $tablename = $wpdb->prefix . 'vipps_login_sessions';
        $content = json_encode($input);
        // If there is a *collision* in the sessionkey, something is really weird with the universe, but hey: try 1000 times. IOK 2019-09-18
        while ($count < 1000 && !$wpdb->insert($tablename,array('state'=>$sessionkey,'expire'=>$expiretime,'content'=>$content), array('%s','%s','%s'))) {
            $count++;
            $sessionkey = base64_encode(hash('sha256',random_bytes(256), true));
        }
        static::clean();
        return new VippsSession($sessionkey,$input);
    }
    public static function get($sessionkey) {
        global $wpdb;
        $tablename = $wpdb->prefix . 'vipps_login_sessions';
        $q = $wpdb->prepare("SELECT content FROM `{$tablename}` WHERE state=%s", $sessionkey);
        $exists = $wpdb->get_var($q);
        if (!$exists) return null;
        $content = json_decode($exists,true);
        return new VippsSession($sessionkey,$content);
    }
    public function destroy() {
        global $wpdb;
        if ($this->destroyed) return;
        $tablename = $wpdb->prefix . 'vipps_login_sessions';
        $q = $wpdb->prepare("DELETE FROM `{$tablename}` WHERE state = %s ", $this->sessionkey);
        $wpdb->query($q);
        $this->destroyed = true;
    }
    public static function clean() {
        // Delete old sessions.
        global $wpdb;
        $tablename = $wpdb->prefix . 'vipps_login_sessions';
        $q = $wpdb->prepare("DELETE FROM `{$tablename}` WHERE expire < %s ", gmdate('Y-m-d H:i:s', time()));
        $wpdb->query($q);
    }
    public static function destroy_all() {
        // Delete ALL sessions. This would be for cleanup . IOK 2019-10-14
        global $wpdb;
        $tablename = $wpdb->prefix . 'vipps_login_sessions';
        $q = $wpdb->prepare("DELETE FROM `{$tablename}` WHERE %d",1);
        $wpdb->query($q);
    }

    public function extend ($expire) {
        global $wpdb;
        if ($this->destroyed) return;
        $newexpire = "";
        $newexpire = gmdate('Y-m-d H:i:s', time() + intval($expire));
        $q = $wpdb->prepare("UPDATE `{$tablename}` SET expire=%s WHERE state=%s", $newexpire, $this->sessionkey);
        $wpdb->query($q);
        return $this;
    }
    public function update($data,$expire=0) {
        global $wpdb;
        if ($this->destroyed) return;
        $newexpire = "";
        if (intval($expire)) $newexpire = gmdate('Y-m-d H:i:s', time() + $expire);
        $newcontent = json_encode($data);

        $tablename = $wpdb->prefix . 'vipps_login_sessions';
        $q = "";
        if ($newexpire) {
            $q = $wpdb->prepare("UPDATE `{$tablename}` SET content=%s,expire=%s WHERE state=%s", $newcontent,$newexpire, $this->sessionkey);
        } else { 
            $q = $wpdb->prepare("UPDATE `{$tablename}` SET content=%s WHERE state=%s", $newcontent, $this->sessionkey);
        }
        $wpdb->query($q);
        $this->contents=$data;
        return $this;
    }
    public function set($key,$value) {
        $this->contents[$key] = $value;
        return $this->update($this->contents);
    }

}
