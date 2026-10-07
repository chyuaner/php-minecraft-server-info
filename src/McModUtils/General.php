<?php
namespace McModUtils;

use GdImage;
use MinecraftBanner\MinecraftBanner;

class General
{
    const WIDTH = 650;
    const HEIGHT = 80;
    const PADDING = 3;

    const FAVICON_SIZE = 64;

    const TITLE_SIZE = 13;
    const MOTD_SIZE = 12;
    const PLAYERS_SIZE = 14;
    const PING_WIDTH = 36;
    const PING_HEIGHT = 29;

    const PING_WELL = 150;
    const PING_GOOD = 300;
    const PING_WORSE = 400;
    const PING_WORST = 500;

    const COLOR_CHAR = '§';
    const COLORS = [
        '0' => [0, 0, 0],       // Black
        '1' => [0, 0, 170],     // Dark Blue
        '2' => [0, 170, 0],     // Dark Green
        '3' => [0, 170, 170],   // Dark Aqua
        '4' => [170, 0, 0],     // Dark Red
        '5' => [170, 0, 170],   // Dark Purple
        '6' => [255, 170, 0],   // Gold
        '7' => [170, 170, 170], // Gray
        '8' => [85, 85, 85],    // Dark Gray
        '9' => [85, 85, 255],   // Blue
        'a' => [85, 255, 85],   // Green
        'b' => [85, 255, 255],  // Aqua
        'c' => [255, 85, 85],   // Red
        'd' => [255, 85, 255],  // Light Purple
        'e' => [255, 255, 85],  // Yellow
        'f' => [255, 255, 255], // White
    ];

    public static function getFontFile() : string {
        if (!empty($GLOBALS['config']['banner_font']) && file_exists($GLOBALS['config']['banner_font'])) {
            return $GLOBALS['config']['banner_font'];
        }
        $localArkPixel = __DIR__ . '/../../res/fonts/ark-pixel-12px-proportional-zh_tw.otf';
        if (file_exists($localArkPixel)) {
            return $localArkPixel;
        }
        $localUnifont = __DIR__ . '/../../res/fonts/unifont.ttf';
        if (file_exists($localUnifont)) {
            return $localUnifont;
        }
        $vendorFont = __DIR__ . '/../../vendor/games647/minecraft-banner-generator/src/minecraft.ttf';
        if (file_exists($vendorFont)) {
            return $vendorFont;
        }
        return 'unifont';
    }

    public static function getEmojiDir() : string {
        if (!empty($GLOBALS['config']['banner_emojis_path']) && is_dir($GLOBALS['config']['banner_emojis_path'])) {
            return rtrim($GLOBALS['config']['banner_emojis_path'], '/');
        }
        return __DIR__ . '/../../res/emojis';
    }

    public static function emojiToHex(string $emoji) : string {
        $codepoints = [];
        $utf32 = mb_convert_encoding($emoji, 'UTF-32BE', 'UTF-8');
        for ($i = 0; $i < strlen($utf32); $i += 4) {
            $cp = unpack('N', substr($utf32, $i, 4))[1];
            if ($cp !== 0xFE0F) {
                $codepoints[] = dechex($cp);
            }
        }
        return implode('-', $codepoints);
    }

    public static function getEmojiImage(string $emoji) : ?GdImage {
        $emojiDir = self::getEmojiDir();
        $hex = self::emojiToHex($emoji);
        if (empty($hex)) {
            return null;
        }

        $file = $emojiDir . '/' . $hex . '.png';
        if (!file_exists($file)) {
            if (file_exists($emojiDir . '/' . $hex . '-fe0f.png')) {
                $file = $emojiDir . '/' . $hex . '-fe0f.png';
            } else {
                $cdnUrl = "https://cdnjs.cloudflare.com/ajax/libs/twemoji/14.0.2/72x72/{$hex}.png";
                $content = @file_get_contents($cdnUrl);
                if ($content) {
                    @file_put_contents($file, $content);
                }
            }
        }

        if (file_exists($file)) {
            $img = @imagecreatefrompng($file);
            return $img ?: null;
        }

        return null;
    }

    public static function getBackgroundCanvas(int $width, int $height, $background = null) {
        return MinecraftBanner::getBackgroundCanvas($width, $height, $background);
    }

    public static function getPingImage(float|int $ping) {
        $base = __DIR__ . '/../../vendor/games647/minecraft-banner-generator/src/img/ping/';
        if ($ping < 0) {
            $path = $base . '-1.png';
        } else if ($ping > 0 && $ping <= self::PING_WELL) {
            $path = $base . '5.png';
        } else if ($ping <= self::PING_GOOD) {
            $path = $base . '4.png';
        } else if ($ping <= self::PING_WORSE) {
            $path = $base . '3.png';
        } else if ($ping <= self::PING_WORST) {
            $path = $base . '2.png';
        } else {
            $path = $base . '1.png';
        }

        return file_exists($path) ? imagecreatefrompng($path) : null;
    }

    public static function getDefaultFavicon() {
        $path = __DIR__ . '/../../vendor/games647/minecraft-banner-generator/src/img/favicon.png';
        return file_exists($path) ? imagecreatefrompng($path) : null;
    }

    public static function server(
        string $address,
        string $motd = "§cOffline Server",
        int|string $players = -1,
        int|string $max_players = -1,
        $favicon = null,
        $background = null,
        float|int $ping = 0
    ) {
        $canvas = self::getBackgroundCanvas(self::WIDTH, self::HEIGHT, $background);
        if ($favicon === null) {
            $favicon = self::getDefaultFavicon();
        }

        $favicon_posY = (self::HEIGHT - self::FAVICON_SIZE) / 2;
        if ($favicon) {
            imagecopy($canvas, $favicon, self::PADDING, $favicon_posY, 0, 0, self::FAVICON_SIZE, self::FAVICON_SIZE);
        }

        // 矩形文繞圖：固定文字區塊起始 X 座標，與 Favicon 保持清晰矩形間距
        $startX = self::PADDING + self::FAVICON_SIZE + 9;
        $fontFile = self::getFontFile();

        // 1. 伺服器標題（位址）
        $white = imagecolorallocate($canvas, 255, 255, 255);
        $titleY = $favicon_posY + self::PADDING * 2 + self::TITLE_SIZE;
        imagettftext($canvas, self::TITLE_SIZE, 0, $startX, $titleY, $white, $fontFile, trim($address));

        // 2. MOTD（文字、顏色代碼與彩色 Emoji）
        self::renderMotd($canvas, $motd, $startX, 50, $fontFile);

        // 3. Ping 圖示
        $pingImg = self::getPingImage($ping);
        $ping_posX = self::WIDTH - self::PING_WIDTH - self::PADDING;
        if ($pingImg) {
            imagecopy($canvas, $pingImg, $ping_posX, $favicon_posY, 0, 0, self::PING_WIDTH, self::PING_HEIGHT);
            imagedestroy($pingImg);
        }

        // 4. 線上玩家人數與 Ping 毫秒
        $pingSuffix = '';
        if (is_numeric($max_players) && $ping > 0) {
            $pingSuffix = '    ' . round($ping, 0) . 'ms';
        }
        $playersText = $players . ' / ' . $max_players . $pingSuffix;

        $box = imagettfbbox(self::PLAYERS_SIZE, 0, $fontFile, $playersText);
        $text_width = abs($box[4] - $box[0]);
        $posY = $favicon_posY + (self::PING_HEIGHT / 2) + self::PLAYERS_SIZE / 2;
        $posX = $ping_posX - $text_width - self::PADDING / 2;
        imagettftext($canvas, self::PLAYERS_SIZE, 0, $posX, $posY, $white, $fontFile, $playersText);

        return $canvas;
    }

    public static function renderMotd($canvas, string $motd, int $startX, int $startY, string $fontFile) : void {
        $nextX = $startX;
        $nextY = $startY;
        $lastColor = [255, 255, 255];
        $colors = self::COLORS;

        $components = explode(self::COLOR_CHAR, $motd);

        foreach ($components as $index => $component) {
            if ($component === '') {
                continue;
            }

            if ($index === 0) {
                $text = $component;
            } else {
                $colorCode = strtolower($component[0]);
                if (isset($colors[$colorCode])) {
                    $lastColor = $colors[$colorCode];
                } elseif ($colorCode === 'r') {
                    $lastColor = [255, 255, 255];
                }
                $text = substr($component, 1);
            }

            if ($text === '') {
                continue;
            }

            $color = imagecolorallocate($canvas, $lastColor[0], $lastColor[1], $lastColor[2]);

            $lines = explode("\n", $text);
            foreach ($lines as $lineIdx => $line) {
                if ($lineIdx > 0) {
                    $nextX = $startX;
                    $nextY += self::PADDING * 2 + self::MOTD_SIZE;
                }

                if ($line === '') {
                    continue;
                }

                $segments = preg_split('/(\p{Extended_Pictographic}(?:\x{FE0F})?)/u', $line, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
                if (empty($segments)) {
                    continue;
                }

                foreach ($segments as $segment) {
                    if (preg_match('/^\p{Extended_Pictographic}/u', $segment)) {
                        $emojiImg = self::getEmojiImage($segment);
                        $emojiSize = 14;
                        if ($emojiImg) {
                            $emojiY = $nextY - 13;
                            imagecopyresampled($canvas, $emojiImg, $nextX, $emojiY, 0, 0, $emojiSize, $emojiSize, imagesx($emojiImg), imagesy($emojiImg));
                            imagedestroy($emojiImg);
                            $nextX += $emojiSize + 2;
                        } else {
                            imagettftext($canvas, self::MOTD_SIZE, 0, $nextX, $nextY, $color, $fontFile, $segment);
                            $box = imagettfbbox(self::MOTD_SIZE, 0, $fontFile, $segment);
                            $nextX += abs($box[4] - $box[0]);
                        }
                    } else {
                        imagettftext($canvas, self::MOTD_SIZE, 0, $nextX, $nextY, $color, $fontFile, $segment);
                        $box = imagettfbbox(self::MOTD_SIZE, 0, $fontFile, $segment);
                        $nextX += abs($box[4] - $box[0]);
                    }
                }
            }
        }
    }

    public static function parseChatComponent(mixed $component) : string {
        if (is_string($component)) {
            return self::normalizeColorCodes($component);
        }

        if (!is_array($component)) {
            return '';
        }

        if (array_is_list($component)) {
            $result = '';
            foreach ($component as $item) {
                $result .= self::parseChatComponent($item);
            }
            return $result;
        }

        $result = '';

        if (!empty($component['color'])) {
            $color = strtolower($component['color']);
            $colorMap = [
                'black' => '§0',
                'dark_blue' => '§1',
                'dark_green' => '§2',
                'dark_aqua' => '§3',
                'dark_red' => '§4',
                'dark_purple' => '§5',
                'gold' => '§6',
                'gray' => '§7',
                'dark_gray' => '§8',
                'blue' => '§9',
                'green' => '§a',
                'aqua' => '§b',
                'red' => '§c',
                'light_purple' => '§d',
                'yellow' => '§e',
                'white' => '§f',
                'reset' => '§f',
            ];

            if (isset($colorMap[$color])) {
                $result .= $colorMap[$color];
            } elseif (str_starts_with($color, '#')) {
                $result .= self::hexToMinecraftColor($color);
            }
        }

        $styleMap = [
            'bold' => '§l',
            'italic' => '§o',
            'underlined' => '§n',
            'strikethrough' => '§m',
            'obfuscated' => '§k',
        ];
        foreach ($styleMap as $style => $code) {
            if (!empty($component[$style])) {
                $result .= $code;
            }
        }

        if (isset($component['text']) && is_string($component['text'])) {
            $result .= self::normalizeColorCodes($component['text']);
        } elseif (isset($component['translate']) && is_string($component['translate'])) {
            $result .= $component['translate'];
        }

        if (!empty($component['extra']) && is_array($component['extra'])) {
            foreach ($component['extra'] as $extra) {
                $result .= self::parseChatComponent($extra);
            }
        }

        return $result;
    }

    public static function normalizeColorCodes(string $text) : string {
        $text = preg_replace_callback('/§x(?:§[0-9a-fA-F]){6}/i', function($matches) {
            $hex = str_ireplace(['§x', '§'], '', $matches[0]);
            return self::hexToMinecraftColor($hex);
        }, $text);

        $text = preg_replace_callback('/§([0-9a-fk-orA-FK-OR])/', function($matches) {
            $code = strtolower($matches[1]);
            if ($code === 'r') {
                return '§f';
            }
            return '§' . $code;
        }, $text);

        return $text;
    }

    public static function hexToMinecraftColor(string $hex) : string {
        $hex = ltrim($hex, '#');
        if (strlen($hex) === 3) {
            $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
        }
        if (strlen($hex) !== 6) {
            return '§f';
        }

        $r = hexdec(substr($hex, 0, 2));
        $g = hexdec(substr($hex, 2, 2));
        $b = hexdec(substr($hex, 4, 2));

        $palette = [
            '0' => [0, 0, 0],
            '1' => [0, 0, 170],
            '2' => [0, 170, 0],
            '3' => [0, 170, 170],
            '4' => [170, 0, 0],
            '5' => [170, 0, 170],
            '6' => [255, 170, 0],
            '7' => [170, 170, 170],
            '8' => [85, 85, 85],
            '9' => [85, 85, 255],
            'a' => [85, 255, 85],
            'b' => [85, 255, 255],
            'c' => [255, 85, 85],
            'd' => [255, 85, 255],
            'e' => [255, 255, 85],
            'f' => [255, 255, 255],
        ];

        $minDist = PHP_INT_MAX;
        $closest = 'f';
        foreach ($palette as $code => $rgb) {
            $dist = ($r - $rgb[0]) ** 2 + ($g - $rgb[1]) ** 2 + ($b - $rgb[2]) ** 2;
            if ($dist < $minDist) {
                $minDist = $dist;
                $closest = $code;
            }
        }

        return '§' . $closest;
    }
}
