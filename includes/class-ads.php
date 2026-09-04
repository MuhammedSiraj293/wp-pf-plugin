<?php
/**
 * TCA Ads Manager — simple sliding ad panel for the single property page.
 * Stores ads as a JSON array in wp_options under 'tca_ads'.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class TCA_Ads_Manager {

    public function __construct() {
        add_action( 'admin_menu',            [ $this, 'add_menu' ] );
        add_action( 'admin_init',            [ $this, 'handle_save' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin' ] );
        add_shortcode( 'tca_ads',            [ $this, 'render_shortcode' ] );
    }

    public function add_menu() {
        add_submenu_page(
            'edit.php?post_type=tca_unit',
            'Ad Manager',
            '📢 Ad Manager',
            'manage_options',
            'tca-ads',
            [ $this, 'render_page' ]
        );
    }

    public function enqueue_admin( $hook ) {
        if ( strpos( $hook, 'tca-ads' ) === false ) return;
        wp_enqueue_media();
    }

    public function handle_save() {
        if ( ! isset( $_POST['tca_ads_nonce'] ) ) return;
        if ( ! wp_verify_nonce( $_POST['tca_ads_nonce'], 'tca_save_ads' ) ) return;
        if ( ! current_user_can( 'manage_options' ) ) return;

        $ads = [];
        $images   = isset( $_POST['ad_image'] )   ? (array) $_POST['ad_image']   : [];
        $titles   = isset( $_POST['ad_title'] )   ? (array) $_POST['ad_title']   : [];
        $wa_nums  = isset( $_POST['ad_wa'] )      ? (array) $_POST['ad_wa']      : [];
        $subtexts = isset( $_POST['ad_subtext'] ) ? (array) $_POST['ad_subtext'] : [];

        foreach ( $images as $i => $img_url ) {
            $ads[] = [
                'image'   => esc_url_raw( $img_url ),
                'title'   => sanitize_text_field( $titles[$i] ?? '' ),
                'subtext' => sanitize_text_field( $subtexts[$i] ?? '' ),
                'wa'      => esc_url_raw( $wa_nums[$i] ?? '' ),
            ];
        }

        update_option( 'tca_ads', $ads );
        add_action( 'admin_notices', function() {
            echo '<div class="notice notice-success is-dismissible"><p>Ads saved!</p></div>';
        });
    }

    /** Render the [tca_ads] shortcode */
    public function render_shortcode() {
        $ads = self::get_ads();
        if ( empty( $ads ) ) return '';

        // Unique ID per shortcode instance so multiple on same page work
        static $instance = 0;
        $instance++;
        $track_id = 'tca-ad-track-' . $instance;
        $dots_id  = 'tca-ad-dots-'  . $instance;
        $count    = count( $ads );

        ob_start(); ?>
        <div class="tca-ad-widget">
            <div class="tca-ad-track" id="<?php echo esc_attr($track_id); ?>">
                <?php foreach ( $ads as $ad ) :
                    $wa_link = ! empty( $ad['wa'] ) ? esc_url( $ad['wa'] ) : '#';
                ?>
                <a class="tca-ad-slide" href="<?php echo esc_url($wa_link); ?>" target="_blank" rel="noopener">
                    <?php if ( ! empty( $ad['image'] ) ) : ?>
                    <img src="<?php echo esc_url( $ad['image'] ); ?>" alt="<?php echo esc_attr( $ad['title'] ?? '' ); ?>" class="tca-ad-img">
                    <?php endif; ?>
                    <?php if ( ! empty( $ad['title'] ) || ! empty( $ad['subtext'] ) ) : ?>
                    <div class="tca-ad-caption">
                        <?php if ( ! empty( $ad['title'] ) ) : ?>
                        <span class="tca-ad-caption-title"><?php echo esc_html( $ad['title'] ); ?></span>
                        <?php endif; ?>
                        <?php if ( ! empty( $ad['subtext'] ) ) : ?>
                        <span class="tca-ad-caption-sub"><?php echo esc_html( $ad['subtext'] ); ?></span>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                </a>
                <?php endforeach; ?>
            </div>
            <?php if ( $count > 1 ) : ?>
            <button type="button" class="tca-ad-nav tca-ad-prev" id="<?php echo esc_attr($track_id); ?>-prev">‹</button>
            <button type="button" class="tca-ad-nav tca-ad-next" id="<?php echo esc_attr($track_id); ?>-next">›</button>
            <div class="tca-ad-dots" id="<?php echo esc_attr($dots_id); ?>">
                <?php for ( $i = 0; $i < $count; $i++ ) : ?>
                <button class="tca-ad-dot<?php echo $i === 0 ? ' active' : ''; ?>" data-slide="<?php echo $i; ?>"></button>
                <?php endfor; ?>
            </div>
            <?php endif; ?>
        </div>
        <?php if ( $count > 1 ) : ?>
        <script>
        (function(){
            const track = document.getElementById('<?php echo esc_js($track_id); ?>');
            const dotsEl = document.getElementById('<?php echo esc_js($dots_id); ?>');
            if (!track) return;
            const dots = dotsEl ? dotsEl.querySelectorAll('.tca-ad-dot') : [];
            let cur = 0, total = <?php echo $count; ?>;
            function goTo(idx){
                cur = (idx + total) % total;
                track.style.transform = 'translateX(-'+(cur*100)+'%)';
                track.style.transition = 'transform 0.45s cubic-bezier(0.25,0.46,0.45,0.94)';
                dots.forEach(function(d,i){ d.classList.toggle('active', i===cur); });
            }
            dots.forEach(function(d){ d.addEventListener('click', function(){ goTo(parseInt(d.dataset.slide)); }); });
            
            const btnPrev = document.getElementById('<?php echo esc_js($track_id); ?>-prev');
            const btnNext = document.getElementById('<?php echo esc_js($track_id); ?>-next');
            let timer = setInterval(function(){ goTo(cur+1); }, 4000);

            if (btnPrev) btnPrev.addEventListener('click', function(){ goTo(cur-1); clearInterval(timer); timer = setInterval(function(){ goTo(cur+1); }, 4000); });
            if (btnNext) btnNext.addEventListener('click', function(){ goTo(cur+1); clearInterval(timer); timer = setInterval(function(){ goTo(cur+1); }, 4000); });
            
        })();
        </script>
        <?php endif;
        return ob_get_clean();
    }

    /** Get all active ads */
    public static function get_ads() {
        return (array) get_option( 'tca_ads', [] );
    }

    public function render_page() {
        $ads = self::get_ads();
        ?>
        <div class="wrap">
            <h1>📢 Ad Manager</h1>
            <p style="color:#666">Ads slide on the single property page. Each ad links to WhatsApp.</p>

            <form method="post" id="tca-ads-form">
                <?php wp_nonce_field( 'tca_save_ads', 'tca_ads_nonce' ); ?>

                <div id="tca-ads-list">
                    <?php foreach ( $ads as $idx => $ad ) : ?>
                        <?php $this->render_ad_row( $idx, $ad ); ?>
                    <?php endforeach; ?>
                </div>

                <button type="button" id="tca-add-ad" class="button" style="margin:16px 0">
                    + Add New Ad
                </button>
                <br>
                <?php submit_button( 'Save Ads' ); ?>
            </form>
        </div>

        <style>
        .tca-ad-row {
            background: #fff;
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 16px;
            position: relative;
        }
        .tca-ad-row .tca-ad-grid {
            display: grid;
            grid-template-columns: 160px 1fr;
            gap: 20px;
            align-items: start;
        }
        .tca-ad-preview {
            width: 160px;
            height: 120px;
            object-fit: cover;
            border-radius: 8px;
            border: 1px solid #ddd;
            display: block;
        }
        .tca-ad-no-img {
            width: 160px;
            height: 120px;
            background: #f4f4f4;
            border: 2px dashed #ccc;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #999;
            font-size: 13px;
            cursor: pointer;
        }
        .tca-ad-fields label { display: block; font-weight: 600; margin: 10px 0 4px; font-size: 12px; text-transform: uppercase; color: #555; }
        .tca-ad-fields input[type=text] { width: 100%; }
        .tca-remove-ad { position: absolute; top: 12px; right: 14px; background: #dc3545; color: #fff; border: none; border-radius: 4px; padding: 4px 10px; cursor: pointer; font-size: 12px; }
        </style>

        <script>
        let adIndex = <?php echo count($ads); ?>;

        function tcaPickImage(idx) {
            const frame = wp.media({ title: 'Select Ad Image', button: { text: 'Use Image' }, multiple: false });
            frame.on('select', function() {
                const att = frame.state().get('selection').first().toJSON();
                document.getElementById('ad_img_url_' + idx).value = att.url;
                const preview = document.getElementById('ad_img_preview_' + idx);
                preview.src = att.url;
                preview.style.display = 'block';
                document.getElementById('ad_no_img_' + idx).style.display = 'none';
            });
            frame.open();
        }

        document.getElementById('tca-add-ad').addEventListener('click', function() {
            const idx = adIndex++;
            const row = document.createElement('div');
            row.className = 'tca-ad-row';
            row.dataset.idx = idx;
            row.innerHTML = `
                <button type="button" class="tca-remove-ad" onclick="this.closest('.tca-ad-row').remove()">Remove</button>
                <div class="tca-ad-grid">
                    <div style="cursor:pointer" onclick="tcaPickImage(${idx})">
                        <div class="tca-ad-no-img" id="ad_no_img_${idx}">Click to select image</div>
                        <img id="ad_img_preview_${idx}" class="tca-ad-preview" src="" style="display:none">
                        <input type="hidden" name="ad_image[]" id="ad_img_url_${idx}" value="">
                        <small style="color:#888;font-size:11px">Click image to change</small>
                    </div>
                    <div class="tca-ad-fields">
                        <label>Ad Title</label>
                        <input type="text" name="ad_title[]" placeholder="e.g. Tara Park – Reem Island">
                        <label>Subtitle / Tagline</label>
                        <input type="text" name="ad_subtext[]" placeholder="e.g. 1-3 BR from 1.6M AED">
                        <label>WhatsApp Link (Full URL with text)</label>
                        <input type="text" name="ad_wa[]" placeholder="e.g. https://wa.me/971505026788?text=Hello...">
                    </div>
                </div>`;
            document.getElementById('tca-ads-list').appendChild(row);
        });
        </script>
        <?php
    }

    private function render_ad_row( $idx, $ad ) {
        $has_img = ! empty( $ad['image'] );
        ?>
        <div class="tca-ad-row" data-idx="<?php echo $idx; ?>">
            <button type="button" class="tca-remove-ad" onclick="this.closest('.tca-ad-row').remove()">Remove</button>
            <div class="tca-ad-grid">
                <div style="cursor:pointer" onclick="tcaPickImage(<?php echo $idx; ?>)">
                    <div class="tca-ad-no-img" id="ad_no_img_<?php echo $idx; ?>" style="<?php echo $has_img ? 'display:none' : ''; ?>">Click to select image</div>
                    <img id="ad_img_preview_<?php echo $idx; ?>" class="tca-ad-preview" src="<?php echo esc_url( $ad['image'] ?? '' ); ?>" style="<?php echo $has_img ? '' : 'display:none'; ?>">
                    <input type="hidden" name="ad_image[]" id="ad_img_url_<?php echo $idx; ?>" value="<?php echo esc_url( $ad['image'] ?? '' ); ?>">
                    <small style="color:#888;font-size:11px">Click image to change</small>
                </div>
                <div class="tca-ad-fields">
                    <label>Ad Title</label>
                    <input type="text" name="ad_title[]" value="<?php echo esc_attr( $ad['title'] ?? '' ); ?>" placeholder="e.g. Tara Park – Reem Island">
                    <label>Subtitle / Tagline</label>
                    <input type="text" name="ad_subtext[]" value="<?php echo esc_attr( $ad['subtext'] ?? '' ); ?>" placeholder="e.g. 1-3 BR from 1.6M AED">
                    <label>WhatsApp Link (Full URL with text)</label>
                    <input type="text" name="ad_wa[]" value="<?php echo esc_attr( $ad['wa'] ?? '' ); ?>" placeholder="e.g. https://wa.me/971505026788?text=Hello...">
                </div>
            </div>
        </div>
        <?php
    }
}

new TCA_Ads_Manager();
