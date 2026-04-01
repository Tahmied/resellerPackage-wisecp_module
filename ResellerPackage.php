<?php
class ResellerPackage extends AddonModule
{
    public $version = "1.0";

    const MAX_PAGES = 5;

    function __construct()
    {
        $this->_name = __CLASS__;
        parent::__construct();
    }

    public function fields()
    {
        $settings = isset($this->config['settings']) ? $this->config['settings'] : [];

        $fields = [
            'add_to_cart_label' => [
                'wrap_width'  => 100,
                'name'        => "Add to Cart Label",
                'description' => "Text shown on the order button (applies to all pages)",
                'type'        => "text",
                'value'       => isset($settings["add_to_cart_label"])
                    ? $settings["add_to_cart_label"]
                    : "Add to Cart",
                'placeholder' => "Add to Cart",
            ],
        ];

        for ($i = 1; $i <= self::MAX_PAGES; $i++) {
            $fields["page_{$i}_page_path"] = [
                'wrap_width'  => 100,
                'name'        => "Page {$i} — Path",
                'description' => "URL path prefix to match for page {$i} "
                    . "(e.g. /category/hosting/) — leave blank to disable this slot",
                'type'        => "text",
                'value'       => isset($settings["page_{$i}_page_path"])
                    ? $settings["page_{$i}_page_path"]
                    : "",
                'placeholder' => "/category/hosting/",
            ];
            $fields["page_{$i}_page_slug"] = [
                'wrap_width'  => 100,
                'name'        => "Page {$i} — Slug",
                'description' => "URL slug to match for page {$i} "
                    . "(e.g. reseller-hosting)",
                'type'        => "text",
                'value'       => isset($settings["page_{$i}_page_slug"])
                    ? $settings["page_{$i}_page_slug"]
                    : "",
                'placeholder' => "reseller-hosting",
            ];
            $fields["page_{$i}_product_ids"] = [
                'wrap_width'  => 100,
                'name'        => "Page {$i} — Product IDs",
                'description' => "Comma-separated product IDs for page {$i} "
                    . "(e.g. 36,33,34)",
                'type'        => "text",
                'value'       => isset($settings["page_{$i}_product_ids"])
                    ? $settings["page_{$i}_product_ids"]
                    : "",
                'placeholder' => "36,33,34",
            ];
        }

        return $fields;
    }

    public function save_fields($fields = [])
    {
        return $fields;
    }

    public function activate()
    {
        return true;
    }

    public function deactivate()
    {
        return true;
    }

    public function adminArea()
    {
        $action = Filter::init("REQUEST/action", "route");
        if (!$action) $action = 'index';

        $variables = [
            'link'     => $this->area_link,
            'dir_link' => $this->url,
            'dir_path' => $this->dir,
            'dir_name' => $this->_name,
            'name'     => $this->lang["meta"]["name"],
            'version'  => $this->config["meta"]["version"],
            'fields'   => $this->fields(),
        ];

        return [
            'page_title'  => 'Reseller Package',
            'breadcrumbs' => [
                ['link' => '', 'title' => 'Reseller Package'],
            ],
            'content' => $this->view($action . ".php", $variables),
        ];
    }

    public function clientArea()
    {
        $action = Filter::init("REQUEST/action", "route");
        if (!$action) $action = 'index';

        $variables = [
            'link'     => $this->area_link,
            'dir_link' => $this->url,
            'dir_path' => $this->dir,
            'dir_name' => $this->_name,
            'name'     => $this->lang["meta"]["name"],
            'version'  => $this->config["meta"]["version"],
            'fields'   => $this->fields(),
        ];

        return [
            'page_title'  => 'Reseller Package',
            'breadcrumbs' => [
                ['link' => '', 'title' => 'Reseller Package'],
            ],
            'content' => $this->view($action . ".php", $variables),
        ];
    }

    private function buildCardsHtml(array $product_ids, string $cart_label): string
    {
        $cards_html = '';

        foreach ($product_ids as $pid) {
            if ($pid <= 0) continue;

            $product = WDB::select("*")
                ->from("products")
                ->where("id", "=", $pid)
                ->build(true)
                ->fetch_assoc();

            $product = ($product && isset($product[0])) ? $product[0] : null;
            if (!$product) continue;

            $translations = WDB::select("*")
                ->from("products_lang")
                ->where("owner_id", "=", $pid)
                ->build(true)
                ->fetch_assoc();

            $translations = $translations ?: [];

            $prices = WDB::select("*")
                ->from("prices")
                ->where("owner_id", "=", $pid)
                ->build(true)
                ->fetch_assoc();

            $prices = $prices ?: [];

            $options    = [];
            if (!empty($product['options'])) {
                $options = json_decode($product['options'], true) ?: [];
            }
            $is_popular = !empty($options['popular']);

            $translation = null;
            foreach ($translations as $t) {
                if (isset($t['lang']) && $t['lang'] === 'en') {
                    $translation = $t;
                    break;
                }
            }
            if (!$translation && !empty($translations)) {
                $translation = $translations[0];
            }

            $title         = $translation
                ? htmlspecialchars($translation['title'], ENT_QUOTES)
                : 'Product ' . $pid;
            $features_raw  = ($translation && !empty($translation['features']))
                ? $translation['features']
                : '';
            $features_html = nl2br($features_raw);

            $first_price = null;
            foreach ($prices as $p) {
                if (
                    isset($p['period'], $p['time']) &&
                    $p['period'] === 'month' &&
                    (int) $p['time'] === 1
                ) {
                    $first_price = $p;
                    break;
                }
            }
            if (!$first_price && !empty($prices)) {
                $first_price = $prices[0];
            }

            $period_label   = 'Monthly';
            $amount_display = '0';

            if ($first_price) {
                $amount         = (float) $first_price['amount'];
                $amount_display = ($amount == floor($amount))
                    ? (string) (int) $amount
                    : number_format($amount, 2);

                $time = (int) $first_price['time'];

                if ($first_price['period'] === 'year') {
                    $period_label = $time === 1 ? 'Yearly' : $time . ' Years';
                } elseif ($first_price['period'] === 'month') {
                    $period_label = $time === 1 ? 'Monthly' : $time . ' Months';
                }
            }

            $product_type = htmlspecialchars($product['type'], ENT_QUOTES);
            $order_link   = '/order-steps/' . $product_type . '/' . $pid;
            $active_class = $is_popular ? ' active' : '';
            $popular_html = $is_popular
                ? '<div class="tablepopular">Popular</div>' . "\n"
                : '';

            $cards_html .=
                '<div class="tablepaket' . $active_class . '" data-aos="fade-up">' . "\n" .
                $popular_html .
                '<div class="tpakettitle">' . $title . '</div>' . "\n" .
                '<div class="paketline"></div>' . "\n" .
                '<h4>' . $period_label . '</h4>' . "\n" .
                '<h3><div class="amount_spot_view"><i class="currpos"></i> $' . $amount_display . '</div></h3>' . "\n" .
                '<div class="paketline"></div>' . "\n" .
                '<div class="clear"></div>' . "\n" .
                '<div class="products_features">' . $features_html . '</div>' . "\n" .
                '<div class="clear"></div>' . "\n" .
                '<div class="paketline"></div>' . "\n" .
                '<a href="' . $order_link . '" class="gonderbtn">' . htmlspecialchars($cart_label, ENT_QUOTES) . '</a>' . "\n" .
                '</div>' . "\n";
        }

        return $cards_html;
    }

    public function ClientAreaOrderGuard($vars = [])
    {
        $current_uri = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '';

        // Only act on order-steps pages
        if (!preg_match('#/order-steps/[^/]+/(\d+)#', $current_uri, $matches)) {
            return '';
        }

        $current_product_id = (int) $matches[1];
        if (!$current_product_id) return '';

        // Build the full list of restricted product IDs across all pages
        $settings           = isset($this->config['settings']) ? $this->config['settings'] : [];
        $all_restricted_ids = [];

        for ($i = 1; $i <= self::MAX_PAGES; $i++) {
            $raw = isset($settings["page_{$i}_product_ids"])
                ? trim($settings["page_{$i}_product_ids"])
                : '';
            if (!$raw) continue;

            $ids = array_values(
                array_filter(
                    array_map('intval', array_map('trim', explode(',', $raw)))
                )
            );
            $all_restricted_ids = array_merge($all_restricted_ids, $ids);
        }

        $all_restricted_ids = array_unique($all_restricted_ids);

        // Not a restricted product — nothing to do
        if (!in_array($current_product_id, $all_restricted_ids, true)) {
            return '';
        }

        // Guest users are never resellers — redirect immediately
        if (empty($this->user['id'])) {
            header("Location: /");
            exit;
        }

        $reseller_group_ids = [13];
        $user_id            = (int) $this->user['id'];

        $row = WDB::select("group_id")
            ->from("users")
            ->where("id", "=", $user_id)
            ->build(true)
            ->fetch_assoc();

        $group_id = ($row && isset($row[0]["group_id"])) ? (int) $row[0]["group_id"] : 0;

        // Allowed reseller — let them through
        if (in_array($group_id, $reseller_group_ids, true)) {
            return '';
        }

        // Everyone else gets a hard redirect
        header("Location: /");
        exit;
    }

    public function ClientAreaEndBody($vars = [])
    {
        if (empty($this->user) || empty($this->user["id"])) {
            return '';
        }

        $user_id = (int) $this->user["id"];

        $reseller_group_ids = [13];

        $user = WDB::select("group_id")
            ->from("users")
            ->where("id", "=", $user_id)
            ->build(true)
            ->fetch_assoc();

        $group_id = ($user && isset($user[0]["group_id"]))
            ? (int) $user[0]["group_id"]
            : 0;

        if (!in_array($group_id, $reseller_group_ids, true)) {
            return '';
        }

        $settings   = isset($this->config['settings']) ? $this->config['settings'] : [];
        $cart_label = isset($settings['add_to_cart_label'])
            ? trim($settings['add_to_cart_label'])
            : 'Add to Cart';

        $current_uri         = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '';
        $matched_product_ids = null;

        for ($i = 1; $i <= self::MAX_PAGES; $i++) {
            $page_path       = isset($settings["page_{$i}_page_path"])
                ? trim($settings["page_{$i}_page_path"])
                : '';
            $page_slug       = isset($settings["page_{$i}_page_slug"])
                ? trim($settings["page_{$i}_page_slug"])
                : '';
            $product_ids_raw = isset($settings["page_{$i}_product_ids"])
                ? trim($settings["page_{$i}_product_ids"])
                : '';

            if (!$product_ids_raw) continue;

            if ($page_path && strpos($current_uri, $page_path) === false) continue;
            if ($page_slug && strpos($current_uri, $page_slug) === false) continue;

            $matched_product_ids = array_values(
                array_filter(
                    array_map('intval', array_map('trim', explode(',', $product_ids_raw)))
                )
            );
            break;
        }

        if (empty($matched_product_ids)) return '';

        $cards_html = $this->buildCardsHtml($matched_product_ids, $cart_label);

        if (!$cards_html) return '';

        $cards_json = json_encode(
            $cards_html,
            JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
        );

        return <<<HTML
<script>
(function () {
    'use strict';
    var container = document.querySelector('.tablopaketler');
    if (!container) return;
    container.insertAdjacentHTML('beforeend', {$cards_json});
}());
</script>
HTML;
    }

    public function upgrade()
    {
        if ($this->config["meta"]["version"] < 1.1) {
        } elseif ($this->config["meta"]["version"] < 1.2) {
        }
        return true;
    }

    public function main()
    {
        $action = Filter::init("REQUEST/action", "route");
        if (!$action) $action = 'index';

        $variables = [
            'link'     => $this->area_link,
            'dir_link' => $this->url,
            'dir_path' => $this->dir,
            'dir_name' => $this->_name,
            'name'     => $this->lang["meta"]["name"],
            'version'  => $this->config["meta"]["version"],
            'fields'   => $this->fields(),
        ];

        return [
            'use_with_theme' => true,
            'page_title'     => 'Reseller Package',
            'breadcrumbs'    => [
                ['link' => '', 'title' => 'Reseller Package'],
            ],
            'content' => $this->view($action . ".php", $variables),
        ];
    }
}

Hook::add("ClientAreaEndBody", 1, [
    "class"  => "ResellerPackage",
    "method" => "ClientAreaEndBody"
]);

Hook::add("ClientAreaHeadMetaTags", 1, [
    "class"  => "ResellerPackage",
    "method" => "ClientAreaOrderGuard"
]);
