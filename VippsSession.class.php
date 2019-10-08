<?php 
// Encapsulate the temporary sessions used for logging in

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

   public function offsetExists ($offset) {
      return isset($this->contents[$offset]);
   }
   public function offsetGet ($offset) {
      return $this->contents[$offset];
   }
   public function offsetSet($offset,$value) {
          $this->contents[$offset] = $value;
          return $this->update($this->contents);
  }
  public function offsetUnset($offset) {
          unset($this->contents[$offset]);
          return $this->update($this->contents);
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
