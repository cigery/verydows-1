<?php
class uploader
{
    private $save_name; //文件保存名
    
    private $ext; //文件扩展名
    
    private $size; //文件大小
    
    private $save_path;//保存路径
    
    private $format_limit = array('.png', '.jpg', '.jpeg', '.gif'); //文件格式限制
    
    private $size_limit = 0; //上传文件大小限制(0表示不限制, 单位为字节)
    
    private $thumb_config = array(); //缩略图配置
    
    private $error; //错误信息
    
    /**
     * 构造函数
     * @param string    $save_path      保存路径
     * @param array     $format_limit   文件格式限制
     * @param array     $size_limit     上传文件大小限制
     */
    public function __construct($save_path, $format_limit = null, $size_limit = 0)
    {
        $this->save_path = $save_path;
        if(!empty($format_limit)) $this->format_limit = $format_limit;
        if(!empty($size_limit)) $this->size_limit = $size_limit;
    }
    
    /**
     * 上传文件
     * @param string $input_name     文件域的name值
     * @param mixed  $auto_rename    是否自动重命名文件(true自动重命名，false使用原文件名，字符串则是自定义保存名)
     * @param string $set_ext  设置扩展名(为空，则使用原扩展名)
     */
    public function upload_file($input_name, $auto_rename = TRUE, $set_ext = null)
    {
        $file = $_FILES[$input_name];
        
        if(is_array($file['name'])) //文件域中是多个文件上传
        {
            foreach($file['name'] as $k => $v)
            {
                if($file['error'][$k] > 0)
                {
                    $this->get_error_msg($file['error'][$k]);
                }
                else
                {
                    if($set_ext != null) $this->ext = $set_ext; else $this->set_file_ext($v); //设置文件扩展名 
                    $this->size = $file['size'][$k];
                    
                    if($auto_rename === FALSE) $auto_rename = $file['name'][$k];
                    $this->set_save_name($auto_rename); //设置文件保存名
                    
                    if($this->verifier()) //如通过验证
                    {
                        if($this->move_file_to_path($file['tmp_name'][$k])) //移动并保存文件
                        {
                            $thumb_config = $this->thumb_config;
                            //如果创建缩略图
                            if(!empty($thumb_config))
                            foreach($thumb_config as $v) $this->create_thumbnail($v['w'], $v['h']);
                        }
                    }
                }
                
                $array_result[$k] = $this->get_result();
            }
            
            return $array_result;
        }
        else //文件域中是单个文件上传
        {
            if($file['error'] > 0)
            {
                $this->get_error_msg($file['error']);
            }
            else
            {
                if($set_ext != null) $this->ext = $set_ext; else $this->set_file_ext($file['name']); //设置文件扩展名
                 
                $this->size = $file['size'];
                
                if($auto_rename === FALSE) $auto_rename = $file['name'];
                $this->set_save_name($auto_rename); //设置文件保存名
                    
                if($this->verifier()) //如通过验证
                {
                    if($this->move_file_to_path($file['tmp_name'])) //移动并保存文件
                    {
                        $thumb_config = $this->thumb_config;
                        //如果创建缩略图
                        if(!empty($thumb_config))
                        foreach($thumb_config as $v) $this->create_thumbnail($v['w'], $v['h']);
                    } 
                }
            }
            
            return $this->get_result();
        }
    }
    
    /**
     * 获取上传结果
     * @return array 返回上传结果数组
     */
    private function get_result()
    {
        $path = str_replace('\\', '/', $this->save_path). $this->save_name;
        return array
        (
            'error' => $this->error,
            'name' => $this->save_name,
            'ext' => $this->ext,
            'size' => $this->size,
            'path' => $path,
            'url' => $GLOBALS['cfg']['http_host'] . '/' . $path,
        );
    }
    
    /**
     * 配置缩略图
     * @param  array $config   缩略图配置(二维数组)
     * @return bool
     */
    public function set_thumbnail($config)
    {
        $this->thumb_config = $config;
    }
    
    /**
     * 创建缩略图
     * @param int $width       缩略图宽
     * @param int $height      缩略图高
     * @return bool
     */
    private function create_thumbnail($width = 0, $height = 0)
    {
        if($width == 0 || $height == 0) return;
        
        $image_file = $this->save_path . $this->save_name;
        
        list($ori_width, $ori_height) = getimagesize($image_file);
        
        $thumb_dir = $this->save_path . $width . 'x' . $height . DS;
        if(!is_dir($thumb_dir)) //如没有该缩略图目录则创建
        {
            if(!mkdir($thumb_dir, 0777, TRUE))
            {
                $this->get_error_msg('ERR_SAVE_PATH'); return;
            }
        }
        
        $thumb_save_path = $thumb_dir . $this->save_name;
        
        if($width >= $ori_width || $height >= $ori_width) //如果原图尺寸过小则拷贝原图不生成缩略图
        {
            copy($image_file, $thumb_save_path);
        }
        else
        {
            $ext = $this->ext;
            switch($ext)
            {
                case '.jpg':
                case '.jpeg':
                    $ori_image = imagecreatefromjpeg($image_file);
                    break;
                    
                case '.png':
                    $ori_image = imagecreatefrompng($image_file);
                    break;
                    
                case '.gif':
                    $ori_image = imagecreatefromgif($image_file);
                    break;
                    
                default:
                    $this->get_error_msg('ERR_THUMB_TYPE');
                    return;
            }
            
            $thumb = imagecreatetruecolor($width, $height);
        
            if($ext == '.gif' || $ext == '.png') //如果是gif或png图片, 则保持其透明度
            {
                imagecolortransparent($thumb, imagecolorallocatealpha($ori_image, 0, 0, 0, 127));
                imagealphablending($thumb, false);
                imagesavealpha($thumb, true);
            }
            
            imagecopyresampled($thumb, $ori_image, 0, 0, 0, 0, $width, $height, $ori_width, $ori_height);
    
            switch($ext)
            {
                case '.jpg':
                case '.jpeg':
                    imagejpeg($thumb, $thumb_save_path, 90);
                    break;
                    
                case '.png':
                    imagepng($thumb, $thumb_save_path);
                    break;
                    
                case '.gif':
                    imagegif($thumb, $thumb_save_path);
                    break;
            }
            
            imagedestroy($ori_image);
            imagedestroy($thumb);
        }
        
        return TRUE;
    }

    /**
     * 移动文件到指定位置
     * @param  string $tmp_name  临时文件
     * @return bool
     */
    private function move_file_to_path($tmp_name)
    {
        $file_save_path = $this->save_path . $this->save_name;
        
        if(move_uploaded_file($tmp_name, $file_save_path))
        {
            $this->get_error_msg(UPLOAD_ERR_OK);
            return TRUE;
        }
        else
        {
            $this->get_error_msg('ERR_FILE_MOVE');
            return FALSE;
        }
    }
    
    
    /**
     * 设置文件保存名
     * @param mixed $auto_name 保存名
     */
    private function set_save_name($auto_name)
    {
        if(TRUE === $auto_name) //重命名上传文件
        {
            $chars = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
            $range = array(-40, -30, -20, -10, 0, 10, 20, 30, 40);
            $name = uniqid() . substr(str_shuffle($chars), $range[mt_rand(0, 8)], 10) . mt_rand(0, 99999);
            $this->save_name = $name . $this->ext;
        }
        else
        {
            if(strtolower(strrchr($auto_name, '.')) == $this->ext) $this->save_name = $auto_name;
            else $this->save_name = $auto_name . $this->ext;;
        }
    }
    
    /**
     * 设置文件扩展名
     * @param string $file_name  上传的原文件名
     */
    private function set_file_ext($file_name)
    {
        $this->ext = strtolower(strrchr($file_name, '.'));
    }
    
    /**
     * 检验上传文件相关属性是否符合要求
     * @return bool
     */
    private function verifier() 
    {
        if(!$this->check_file_ext()) //检测文件扩展名
        {
            $this->get_error_msg('ERR_FILE_TYPE');
            return FALSE;
        }
        elseif(!$this->check_file_size()) //检测文件大小
        {
            $this->get_error_msg('ERR_FILE_SIZE');
            return FALSE;
        }
        elseif(!$this->check_save_path()) //检测保存目录
        {
            $this->get_error_msg('ERR_SAVE_PATH');
            return FALSE;
        }
        else
        {
            return TRUE;
        }
    }
    
    /**
     * 检查文件扩展名是否超出限制
     * @return bool
     */
    private function check_file_ext()
    {
        return in_array(strtolower($this->ext), $this->format_limit);
    }
    
    /**
     * 检查文件大小是否超出限制
     * @return bool
     */
    private function check_file_size()
    {
        if(($this->size_limit) == 0 || ($this->size) <= ($this->size_limit)) return TRUE;
        return FALSE;
    }
    
    /**
     * 检查文件保存路径
     * @return bool
     */
    private function check_save_path()
    {
        $path = str_replace('/', DS, $this->save_path);
        
        if(!is_dir($path)) //如没有该目录则创建
        {
            if(!mkdir($path, 0777, TRUE)) return FALSE;
        }
        
        $this->save_path = substr($path, -1) == DS ? $path : $path . DS;
        
        return TRUE;
    }
    
    /**
     * 获取错误信息
     * @param int  $msg_key  错误信息索引
     */
    private function get_error_msg($msg_key)
    {
        $error_map = self::error_msg_map();
        if(isset($error_map[$msg_key])) $this->error = $error_map[$msg_key];
        else $this->error = $error_map['ERR_UNKNOW'];
    }
    
    /**
     * 错误信息列表
     */
    private static function error_msg_map()
    {
        return array
        ( 
            UPLOAD_ERR_OK => 'success', //上传成功
            UPLOAD_ERR_INI_SIZE => '上传的文件超过了 php.ini 中 upload_max_filesize 选项限制的值',
            UPLOAD_ERR_FORM_SIZE => '上传文件的大小超过了 HTML 表单中 MAX_FILE_SIZE 选项指定的值',
            UPLOAD_ERR_PARTIAL => '文件只有部分被上传',
            UPLOAD_ERR_NO_FILE => '没有文件被上传',
            UPLOAD_ERR_NO_TMP_DIR => '找不到临时文件夹',
            UPLOAD_ERR_CANT_WRITE => '文件写入失败',
            'ERR_FILE_TYPE' => '上传文件类型不允许',
            'ERR_FILE_SIZE' => '上传文件大小超出限制',
            'ERR_SAVE_PATH' => '无法创建上传文件的保存目录',
            'ERR_FILE_MOVE' => '保存上传文件失败',
            'ERR_THUMB_TYPE' => '不支持创建此类型上传文件的缩略图',
            'ERR_UNKNOW' => '未知错误',
        );
    }
}