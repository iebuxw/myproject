<?php
namespace app\admin\controller;

use think\Controller;
use think\facade\Session;
use think\facade\Cache;
use think\Db;

class Auth extends Controller
{
    // GET /admin/auth/captcha
    public function captcha()
    {
        $code = $this->generateCaptchaCode(4);
        $key = md5(uniqid(mt_rand(), true));
        Cache::set('captcha:' . $key, strtolower($code), 300);

        $image = $this->drawCaptchaImage($code);
        $base64 = 'data:image/png;base64,' . base64_encode($image);

        return json(['code' => 0, 'msg' => 'success', 'data' => [
            'captcha_key' => $key,
            'captcha_image' => $base64,
        ]]);
    }

    // POST /admin/auth/login
    public function login()
    {
        $username = input('post.username', '');
        $password = input('post.password', '');
        $captchaKey = input('post.captcha_key', '');
        $captchaCode = input('post.captcha_code', '');

        $ip   = request()->ip();
        $ua   = substr(request()->header('user-agent', ''), 0, 500);

        if (empty($username) || empty($password) || empty($captchaKey) || empty($captchaCode)) {
            $this->logLogin($username, $ip, $ua, 0, '参数错误');
            return json(['code' => 1002, 'msg' => '参数错误', 'data' => null]);
        }

        $cachedCode = Cache::get('captcha:' . $captchaKey);
        Cache::set('captcha:' . $captchaKey, '', 1);
        if (!$cachedCode || strtolower($captchaCode) !== $cachedCode) {
            $this->logLogin($username, $ip, $ua, 0, '验证码错误');
            return json(['code' => 1002, 'msg' => '验证码错误', 'data' => null]);
        }

        $admin = Db::table('admin')->where('username', $username)->where('status', 1)->find();
        if (!$admin || !password_verify($password, $admin['password'])) {
            $this->logLogin($username, $ip, $ua, 0, '用户名或密码错误');
            return json(['code' => 1003, 'msg' => '用户名或密码错误', 'data' => null]);
        }

        Session::set('admin_id', $admin['id']);
        $this->logLogin($username, $ip, $ua, 1, '登录成功');

        return json(['code' => 0, 'msg' => 'success', 'data' => [
            'token' => session_id(),
        ]]);
    }

    // POST /admin/auth/logout
    public function logout()
    {
        Session::delete('admin_id');
        return json(['code' => 0, 'msg' => 'success', 'data' => null]);
    }

    // GET /admin/auth/info
    public function info()
    {
        $adminId = Session::get('admin_id');
        if (!$adminId) {
            return json(['code' => 1001, 'msg' => '未登录', 'data' => null]);
        }

        $admin = Db::table('admin')->field('id,username,avatar,status,created_at')->where('id', $adminId)->find();
        if (!$admin) {
            return json(['code' => 1004, 'msg' => '管理员不存在', 'data' => null]);
        }

        // 获取角色
        $roles = Db::table('admin_role')
            ->alias('ar')
            ->join('role r', 'ar.role_id = r.id')
            ->where('ar.admin_id', $adminId)
            ->where('r.status', 1)
            ->column('r.name');

        // 获取菜单权限
        $menuIds = Db::table('admin_role')
            ->alias('ar')
            ->join('role_menu rm', 'ar.role_id = rm.role_id')
            ->where('ar.admin_id', $adminId)
            ->column('rm.menu_id');

        $menus = [];
        if (!empty($menuIds)) {
            $result = Db::table('menu')
                ->whereIn('id', $menuIds)
                ->where('status', 1)
                ->where('type', '<>', 3)
                ->order('sort', 'asc')
                ->select();
            $menus = is_object($result) ? $result->toArray() : $result;
        }

        // 生成树形菜单
        $menuTree = $this->buildTree($menus);

        return json(['code' => 0, 'msg' => 'success', 'data' => [
            'admin'  => $admin,
            'roles'  => $roles,
            'menus'  => $menuTree,
        ]]);
    }

    private function buildTree(array $menus, int $parentId = 0): array
    {
        $tree = [];
        foreach ($menus as $menu) {
            if ($menu['parent_id'] == $parentId) {
                $children = $this->buildTree($menus, $menu['id']);
                if (!empty($children)) {
                    $menu['children'] = $children;
                }
                $tree[] = $menu;
            }
        }
        return $tree;
    }

    private function generateCaptchaCode(int $length = 4): string
    {
        $chars = '23456789abcdefghjkmnpqrstuvwxyz';
        $code = '';
        for ($i = 0; $i < $length; $i++) {
            $code .= $chars[mt_rand(0, strlen($chars) - 1)];
        }
        return $code;
    }

    private function drawCaptchaImage(string $code): string
    {
        $width = 120;
        $height = 40;
        $img = imagecreatetruecolor($width, $height);

        $bgColor = imagecolorallocate($img, 243, 243, 243);
        imagefill($img, 0, 0, $bgColor);

        // 干扰点
        for ($i = 0; $i < 80; $i++) {
            $color = imagecolorallocate($img, mt_rand(150, 220), mt_rand(150, 220), mt_rand(150, 220));
            imagesetpixel($img, mt_rand(0, $width), mt_rand(0, $height), $color);
        }

        // 干扰线
        for ($i = 0; $i < 4; $i++) {
            $color = imagecolorallocate($img, mt_rand(150, 220), mt_rand(150, 220), mt_rand(150, 220));
            imageline($img, mt_rand(0, $width), mt_rand(0, $height), mt_rand(0, $width), mt_rand(0, $height), $color);
        }

        // 字符
        $fontPath = $this->getFontPath();
        $charWidth = ($width - 20) / strlen($code);
        for ($i = 0; $i < strlen($code); $i++) {
            $color = imagecolorallocate($img, mt_rand(30, 120), mt_rand(30, 120), mt_rand(30, 120));
            $x = 10 + $i * $charWidth + mt_rand(-2, 2);
            if ($fontPath !== '') {
                $fontSize = mt_rand(18, 22);
                $y = mt_rand(22, 30);
                $angle = mt_rand(-15, 15);
                imagettftext($img, $fontSize, $angle, $x, $y, $color, $fontPath, $code[$i]);
            } else {
                $y = mt_rand(8, 14);
                imagestring($img, 5, (int)$x, $y, $code[$i], $color);
            }
        }

        ob_start();
        imagepng($img);
        $data = ob_get_clean();
        imagedestroy($img);

        return $data;
    }

    private function getFontPath(): string
    {
        // 优先使用系统字体，回退到 ThinkPHP 内置字体
        $systemFont = '/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf';
        if (file_exists($systemFont)) {
            return $systemFont;
        }
        $tpFont = dirname(__DIR__, 3) . '/think/library/think/captcha/ttfs/1.ttf';
        if (file_exists($tpFont)) {
            return $tpFont;
        }
        // Windows 常见字体路径
        $winFont = 'C:/Windows/Fonts/arial.ttf';
        if (file_exists($winFont)) {
            return $winFont;
        }
        // 最后回退：使用内置默认（GD 会用默认字体）
        return '';
    }

    private function logLogin(string $username, string $ip, string $ua, int $status, string $message): void
    {
        Db::table('login_log')->insert([
            'username'   => $username,
            'ip'         => $ip,
            'user_agent' => $ua,
            'status'     => $status,
            'message'    => $message,
        ]);
    }
}
