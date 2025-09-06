<?php
declare(strict_types=1);

/**
 * Single Template: Workflows
 * 
 * @package PromptFinder
 * @since 1.0.0
 * 
 * Features:
 * - Uses ACF fields for workflow content
 * - Reads PF_CONFIG (JSON) for feature flags & copy strings (server-side)
 * - Improved variables UX (labels, descriptions, required, first-time hint)
 * - Clean step header (badge via CSS, time chip on the right)
 * - Enhanced security and error handling
 * - Optimized performance with caching
 */

get_header();
the_post();

/* -------------------------------------------------------
   Load PF_CONFIG using optimized helper function
-------------------------------------------------------- */
try {
    $PF_CONFIG = pf_load_config();
    $PF_FLAGS = $PF_CONFIG['feature_flags'] ?? [];
    $PF_COPY = $PF_CONFIG['copy'] ?? [];
} catch (Exception $e) {
    error_log('[PF Single] Config loading error: ' . $e->getMessage());
    $PF_CONFIG = [];
    $PF_FLAGS = [];
    $PF_COPY = [];
}

/* -------------------------------------------------------
   Load from optimized table (primary source)
   All new workflows are saved directly to the optimized table
-------------------------------------------------------- */
$optimized_workflow = null;
if (class_exists('PromptFinderCore')) {
    try {
        $pf_core = new PromptFinderCore();
        $optimized_workflow = $pf_core->get_optimized_workflow(get_the_ID());
        
        if ($optimized_workflow) {
            error_log('[PF Single] Using optimized workflow data for post ID: ' . get_the_ID());
        } else {
            error_log('[PF Single] No optimized workflow data found for post ID: ' . get_the_ID() . ' - using ACF fallback');
        }
    } catch (Exception $e) {
        error_log('[PF Single] Optimized workflow loading error: ' . $e->getMessage());
    }
}

/* -------------------------------------------------------
   ACF fields (content per workflow) - with error handling
   Use optimized table if available, fallback to ACF
-------------------------------------------------------- */
try {
    if ($optimized_workflow) {
        // Use optimized table data
        $summary = $optimized_workflow['summary'] ?? '';
        $use_case = function_exists('get_field') ? get_field('use_case') : ''; // Not in optimized table yet
        $version = $optimized_workflow['version'] ?? '';
        $lastest_update = $optimized_workflow['lastest_update'] ?? '';
        $steps = $optimized_workflow['steps'] ?? [];
        $has_steps = is_array($steps) && count($steps) > 0;
        $total_steps = $has_steps ? count($steps) : 0;
        
        // New ACF fields
        $stable_version = function_exists('get_field') ? get_field('stable_version') : false;
        $auto_update_allowed = function_exists('get_field') ? get_field('auto_update_allowed') : false;
        $changelog = function_exists('get_field') ? get_field('changelog') : '';
        
        // Update usage count from optimized table
        $usage_count = $optimized_workflow['usage_count'] ?? 0;
    } else {
        // Fallback to ACF fields
        $summary = function_exists('get_field') ? get_field('Summary') : '';
        $use_case = function_exists('get_field') ? get_field('use_case') : '';
        $version = function_exists('get_field') ? get_field('Version') : '';
        $lastest_update = function_exists('get_field') ? get_field('lastest_update') : ''; // returns d/m/Y per ACF
        $steps = function_exists('get_field') ? get_field('steps') : []; // repeater
        $has_steps = is_array($steps) && count($steps) > 0;
        $total_steps = $has_steps ? count($steps) : 0;
        
        // New ACF fields
        $stable_version = function_exists('get_field') ? get_field('stable_version') : false;
        $auto_update_allowed = function_exists('get_field') ? get_field('auto_update_allowed') : false;
        $changelog = function_exists('get_field') ? get_field('changelog') : '';
        
        $usage_count = 0;
    }
} catch (Exception $e) {
    error_log('[PF Single] ACF fields error: ' . $e->getMessage());
    $summary = '';
    $use_case = '';
    $version = '';
    $lastest_update = '';
    $steps = [];
    $has_steps = false;
    $total_steps = 0;
    $stable_version = false;
    $auto_update_allowed = false;
    $changelog = '';
    $usage_count = 0;
}

/* Value-Highlights - with error handling */
try {
    $pain_point = function_exists('get_field') ? get_field('pain_point') : '';
    $expected_outcome = function_exists('get_field') ? get_field('expected_outcome') : '';
    $time_saved_min = function_exists('get_field') ? get_field('time_saved_min') : 0; // int
    $difficulty_wo_ai = function_exists('get_field') ? get_field('difficulty_without_ai') : 0; // 1–5
    $diff = (int) ($difficulty_wo_ai ?: 0);
    $diff = max(0, min($diff, 5));
} catch (Exception $e) {
    error_log('[PF Single] Value highlights error: ' . $e->getMessage());
    $pain_point = '';
    $expected_outcome = '';
    $time_saved_min = 0;
    $difficulty_wo_ai = 0;
    $diff = 0;
}

/* -------------------------------------------------------
   GATING – robuste Priorität & Hilfsfunktionen
   Priorität: ACF > PF_CONFIG > Defaults
   Felder:
   - access_mode: 'free' | 'half_locked' | 'pro'
   - free_step_limit: int (ab welchem Step gelockt wird, Standard 1)
   - login_required: bool (bei half_locked: Login notwendig?)
-------------------------------------------------------- */

// 1) ACF
$acf_access_mode     = is_string(get_field('access_mode')) ? trim(strtolower(get_field('access_mode'))) : '';
$acf_free_step_limit = get_field('free_step_limit');
$acf_free_step_limit = is_numeric($acf_free_step_limit) ? (int)$acf_free_step_limit : null;
$acf_login_required  = (bool) get_field('login_required');

// 2) PF_CONFIG Defaults (optional)
$cfg_defaults = $PF_CONFIG['workflow_defaults'] ?? [];
$cfg_access_mode     = isset($cfg_defaults['access_mode']) ? trim(strtolower((string)$cfg_defaults['access_mode'])) : '';
$cfg_free_step_limit = isset($cfg_defaults['free_step_limit']) ? (int)$cfg_defaults['free_step_limit'] : null;
$cfg_login_required  = isset($cfg_defaults['login_required']) ? (bool)$cfg_defaults['login_required'] : false;

// 3) Harte Defaults
$def_access_mode     = 'free';
$def_free_step_limit = 1;
$def_login_required  = false;

// 4) Effektive Werte (ACF > CFG > DEF)
$ACCESS_MODE     = $acf_access_mode ?: ($cfg_access_mode ?: $def_access_mode);
$FREE_STEP_LIMIT = ($acf_free_step_limit !== null) ? $acf_free_step_limit
                  : (($cfg_free_step_limit !== null) ? $cfg_free_step_limit : $def_free_step_limit);
$LOGIN_REQUIRED  = ($acf_login_required !== null) ? (bool)$acf_login_required
                  : ($cfg_login_required ?? $def_login_required);

// 5) User-Plan - using function from functions.php
$USER_PLAN = pf_get_user_plan();
$viewer_logged_in  = is_user_logged_in();
$is_pro_user       = ($USER_PLAN === 'pro');

/**
 * Step-Lock-Helper (1-basiert)
 * 
 * @since 1.0.0
 * @param int $idx Step index (1-based)
 * @param string $mode Access mode ('free', 'half_locked', 'pro')
 * @param string $userPlan User plan ('guest', 'free', 'pro')
 * @param int $limit Free step limit
 * @param bool $loginRequired Login required for locked steps
 * @param bool $loggedIn User is logged in
 * @return bool True if step is locked
 */
function pf_step_is_locked(int $idx, string $mode, string $userPlan, int $limit, bool $loginRequired, bool $loggedIn): bool {
    try {
        // Validate inputs
        if ($idx < 1) return false;
        
        if ($mode === 'free') return false;                 // alles frei
        if ($userPlan === 'pro') return false;              // Pro sieht alles
        if ($mode === 'pro') return true;                   // komplette Paywall (früher Abbruch, hier fallback)
        
        if ($mode === 'half_locked') {
            $limit = max(1, $limit);
            if ($idx <= $limit) return false;                 // innerhalb der freien Steps immer frei
            // ab dem ersten gelockten Step:
            if ($loginRequired) {
                // Login ist Pflicht – gelockt, solange nicht eingeloggt
                return !$loggedIn;
            }
            // kein Login zwingend – gelockt für alle außer Pro (oben abgefangen)
            return true;
        }
        
        // Unbekannter Modus → nichts sperren
        return false;
    } catch (Exception $e) {
        error_log('[PF Single] Step lock check error: ' . $e->getMessage());
        return false; // Fail open for safety
    }
}

/* -------------------------------------------------------
   Favoriten-Status - with error handling
-------------------------------------------------------- */
$is_fav = false;
try {
    if (is_user_logged_in()) {
        $f = get_user_meta(get_current_user_id(), 'pf_favs', true);
        $is_fav = is_array($f) && in_array(get_the_ID(), $f, true);
    }
} catch (Exception $e) {
    error_log('[PF Single] Favorites check error: ' . $e->getMessage());
    $is_fav = false;
}
?>
<div class="pf-workflow pf-workflows">
  <header class="pf-header">
    <h1 class="pf-title"><?php the_title(); ?></h1>
	  
<?php
/* ===== Mode-Label vorbereiten (MUSS vor dem Rendern passieren) ===== */
$mode_label = 'Free';
$mode_sub   = '';
if ($ACCESS_MODE === 'pro') {
  $mode_label = 'Pro';
  $mode_sub   = $PF_COPY['badge_pro_sub'] ?? 'Members only';
} elseif ($ACCESS_MODE === 'half_locked') {
  $mode_label = $PF_COPY['badge_limited'] ?? 'Limited';
  $free_txt = ($FREE_STEP_LIMIT === 1)
    ? ($PF_COPY['badge_limited_1'] ?? '1 step free')
    : str_replace('{n}', (string)$FREE_STEP_LIMIT, ($PF_COPY['badge_limited_n'] ?? '{n} steps free'));
  if (!empty($LOGIN_REQUIRED)) {
    $login_txt = $PF_COPY['badge_login_needed'] ?? 'Login needed';
    $mode_sub  = $free_txt . ' • ' . $login_txt;
  } else {
    $mode_sub  = $free_txt;
  }
} else {
  $mode_label = $PF_COPY['badge_free'] ?? 'Free';
  $mode_sub   = $PF_COPY['badge_free_sub'] ?? 'All steps unlocked';
}

/* Optional: Legende schalten – akzeptiere beide Keys */
$show_legend = !empty($PF_FLAGS['mode_legend']) || !empty($PF_FLAGS['gating']);
?>

<!-- ===== kompakte Headbar: links Badge/Legend, rechts Save ===== -->
<div class="pf-headbar">
  <div class="pf-headbar-left">
    <div class="pf-modebadge" data-mode="<?php echo esc_attr($ACCESS_MODE); ?>">
      <span class="pf-modebadge__label"><?php echo esc_html($mode_label); ?></span>
      <?php if ($mode_sub): ?>
        <span class="pf-modebadge__sub"><?php echo esc_html($mode_sub); ?></span>
      <?php endif; ?>
    </div>

  </div>

  <div class="pf-headbar-right">
    <button class="pf-fav-btn <?php echo $is_fav ? 'is-on' : ''; ?>"
            data-post-id="<?php echo esc_attr(get_the_ID()); ?>"
            aria-pressed="<?php echo $is_fav ? 'true' : 'false'; ?>">
      <span class="pf-fav-ico">♥</span>
      <span class="pf-fav-label"><?php echo $is_fav ? 'Saved' : 'Save'; ?></span>
    </button>
  </div>
</div>


    <?php if ($summary): ?>
      <p class="pf-summary"><?php echo nl2br(esc_html($summary)); ?></p>
    <?php endif; ?>

    <?php if (!empty($PF_CONFIG['layout']['show_info_pills'])): ?>
      <ul class="pf-info pf-info--icons">
        <?php if ($use_case): ?>
          <li>
            <span class="pf-pill-ico" aria-hidden="true">
              <svg viewBox="0 0 24 24" width="16" height="16" fill="currentColor"><path d="M3 6h18v12H3zM5 8v8h14V8z"/></svg>
            </span>
            <span class="pf-pill-label"><?php echo esc_html($PF_COPY['pill_use_case'] ?? 'Use case'); ?>:</span>
            <span class="pf-pill-value"><?php echo esc_html($use_case); ?></span>
          </li>
        <?php endif; ?>

        <?php if ($version): ?>
          <li>
            <span class="pf-pill-ico" aria-hidden="true">
              <svg viewBox="0 0 24 24" width="16" height="16" fill="currentColor"><path d="M3 5h18v2H3zm0 6h18v2H3zm0 6h18v2H3z"/></svg>
            </span>
            <span class="pf-pill-label"><?php echo esc_html($PF_COPY['pill_version'] ?? 'Version'); ?>:</span>
            <span class="pf-pill-value">
              <?php echo esc_html($version); ?>
              <?php if ($stable_version): ?>
                <span class="pf-badge pf-badge--stable" title="<?php esc_attr_e('Stable version', 'prompt-finder'); ?>">Stable</span>
              <?php endif; ?>
            </span>
          </li>
        <?php endif; ?>

        <?php if ($lastest_update): ?>
          <li>
            <span class="pf-pill-ico" aria-hidden="true">
              <svg viewBox="0 0 24 24" width="16" height="16" fill="currentColor"><path d="M12 8v5l4 2 .8-1.8-3.2-1.6V8zM12 2a10 10 0 1 0 10 10A10.012 10.012 0 0 0 12 2z"/></svg>
            </span>
            <span class="pf-pill-label"><?php echo esc_html($PF_COPY['pill_updated'] ?? 'Updated'); ?>:</span>
            <span class="pf-pill-value"><?php echo esc_html($lastest_update); ?></span>
          </li>
        <?php endif; ?>

        <?php if ($total_steps > 0): ?>
          <li>
            <span class="pf-pill-ico" aria-hidden="true">
              <svg viewBox="0 0 24 24" width="16" height="16" fill="currentColor"><path d="M4 17h6v2H4zm0-6h10v2H4zm0-6h16v2H4z"/></svg>
            </span>
            <span class="pf-pill-label"><?php echo esc_html($PF_COPY['pill_steps'] ?? 'Steps'); ?>:</span>
            <span class="pf-pill-value"><?php echo (int)$total_steps; ?></span>
          </li>
        <?php endif; ?>

        <?php
          $eta_total = 0;
          if (is_array($steps)) {
            foreach ($steps as $s) { $eta_total += (int)($s['estimated_time_min'] ?? 0); }
          }
        ?>
        <?php if ($eta_total): ?>
          <li>
            <span class="pf-pill-ico" aria-hidden="true">
              <svg viewBox="0 0 24 24" width="16" height="16" fill="currentColor"><path d="M12 8h1v5h-5v-1h4V8zm0-6a10 10 0 1 0 10 10A10.012 10.012 0 0 0 12 2z"/></svg>
            </span>
            <span class="pf-pill-label">Total time:</span>
            <span class="pf-pill-value"><?php echo (int)$eta_total; ?> min</span>
          </li>
        <?php endif; ?>
      </ul>
    <?php endif; ?>

    <?php if ($changelog && !empty($PF_FLAGS['show_changelog'])): ?>
      <details class="pf-changelog pf-tile" style="margin-top: 1rem;">
        <summary>
          <strong><?php echo esc_html($PF_COPY['changelog_title'] ?? 'What\'s new in this version'); ?></strong>
        </summary>
        <div class="pf-changelog-content">
          <?php echo nl2br(esc_html($changelog)); ?>
        </div>
      </details>
    <?php endif; ?>

    <?php if (!empty($PF_CONFIG['layout']['show_thumbnail']) && has_post_thumbnail()): ?>
      <div class="pf-thumb"><?php the_post_thumbnail('large'); ?></div>
    <?php endif; ?>

    <?php
      $show_value_panel = isset($PF_FLAGS['value_panel']) ? !empty($PF_FLAGS['value_panel']) : true;
      if ( $show_value_panel && ($pain_point || $expected_outcome || $time_saved_min || $diff) ):
    ?>
      <section class="pf-value" aria-label="Why this helps">
        <ul class="pf-value-grid">
          <?php if ($pain_point): ?>
            <li class="pf-value-item">
              <span class="pf-value-ico" aria-hidden="true">
                <svg viewBox="0 0 24 24" width="20" height="20" fill="currentColor"><path d="M12 2a10 10 0 1 0 .001 20.001A10 10 0 0 0 12 2Zm1 14h-2v-2h2v2Zm0-4h-2V6h2v6Z"/></svg>
              </span>
              <span class="pf-value-kicker"><?php echo esc_html($PF_COPY['value_pain'] ?? 'Pain Points'); ?></span>
              <div class="pf-value-text"><?php echo nl2br(esc_html($pain_point)); ?></div>
            </li>
          <?php endif; ?>

          <?php if ($expected_outcome): ?>
            <li class="pf-value-item">
              <span class="pf-value-ico" aria-hidden="true">
                <svg viewBox="0 0 24 24" width="20" height="20" fill="currentColor"><path d="M9 16.2 4.8 12l-1.4 1.4L9 19 21 7l-1.4-1.4z"/></svg>
              </span>
              <span class="pf-value-kicker"><?php echo esc_html($PF_COPY['value_outcome'] ?? 'Expected Outcome'); ?></span>
              <div class="pf-value-text"><?php echo nl2br(esc_html($expected_outcome)); ?></div>
            </li>
          <?php endif; ?>

          <?php if ($time_saved_min): ?>
            <li class="pf-value-item">
              <span class="pf-value-ico" aria-hidden="true">
                <svg viewBox="0 0 24 24" width="20" height="20" fill="currentColor"><path d="M12 8h1v5h-5v-1h4V8zm0-6a10 10 0 1 0 10 10A10.012 10.012 0 0 0 12 2z"/></svg>
              </span>
              <span class="pf-value-kicker"><?php echo esc_html($PF_COPY['value_time'] ?? 'Time saved'); ?></span>
              <div class="pf-value-text"><strong><?php echo (int)$time_saved_min; ?> min</strong> per run</div>
            </li>
          <?php endif; ?>

          <?php if ($diff): ?>
            <li class="pf-value-item">
              <span class="pf-value-ico" aria-hidden="true">
                <svg viewBox="0 0 24 24" width="20" height="20" fill="currentColor"><path d="M12 17.27 18.18 21l-1.64-7.03L22 9.24l-7.19-.62L12 2 9.19 8.62 2 9.24l5.46 4.73L5.82 21z"/></svg>
              </span>
              <span class="pf-value-kicker"><?php echo esc_html($PF_COPY['value_diff'] ?? 'Difficulty w/o AI'); ?></span>
              <div class="pf-value-text" title="How difficult this task is without AI">
                <?php
                  $filled = str_repeat('★', $diff);
                  $empty  = str_repeat('☆', 5 - $diff);
                  echo '<span class="pf-diff-stars">'.$filled.$empty.'</span>';
                  echo '<span class="pf-diff-hint">(' . $diff . '/5 = ' . ($diff <= 2 ? 'easy' : ($diff == 3 ? 'medium' : 'hard')) . ')</span>';
                ?>
              </div>
            </li>
          <?php endif; ?>
        </ul>
      </section>
    <?php endif; ?>
	  

    <?php if ($total_steps > 1 && !empty($PF_FLAGS['howto_box'])): ?>
      <?php $howto_pref_key = 'pf_hide_howto_' . get_the_ID(); ?>
      <details class="pf-howto pf-tile" data-pref-key="<?php echo esc_attr($howto_pref_key); ?>" open>
        <summary>
          <?php echo esc_html($PF_COPY['howto_title'] ?? 'How to use this workflow:'); ?>
        </summary>

        <?php $items = $PF_COPY['howto_items'] ?? []; if ($items && is_array($items)): ?>
          <ol>
            <?php foreach ($items as $item): ?>
              <li><?php echo wp_kses_post($item); ?></li>
            <?php endforeach; ?>
          </ol>
        <?php endif; ?>

        <button type="button" class="pf-howto-hide" data-action="hide-howto">
          Don’t show again
        </button>
      </details>
    <?php endif; ?>

    <?php if ( current_user_can('manage_options') ): ?>
      <details class="pf-debug" style="margin:1rem 0;padding:1rem;border:1px dashed #bbb;border-radius:12px;">
        <summary style="cursor:pointer;font-weight:600;">Workflow Gate – Debug</summary>
        <div style="font-family:ui-monospace, SFMono-Regular, Menlo, monospace; font-size:.9rem; line-height:1.5;">
          <strong>Effective:</strong>
          <pre><?php echo esc_html(print_r([
            'ACCESS_MODE' => $ACCESS_MODE,
            'FREE_STEP_LIMIT' => $FREE_STEP_LIMIT,
            'LOGIN_REQUIRED' => $LOGIN_REQUIRED,
            'USER_PLAN' => $USER_PLAN,
          ], true)); ?></pre>

          <strong>ACF Raw:</strong>
          <pre><?php echo esc_html(print_r([
            'access_mode' => get_field('access_mode'),
            'free_step_limit' => get_field('free_step_limit'),
            'login_required' => get_field('login_required'),
          ], true)); ?></pre>

          <strong>PF_CONFIG Defaults:</strong>
          <pre><?php echo esc_html(print_r($cfg_defaults, true)); ?></pre>
        </div>
      </details>
    <?php endif; ?>

  </header>

  <?php
  /* -------------------------------------------------------
     FULL-LOCK (Pro): zentrale Paywall und Steps nicht rendern
     - Pro-User sehen alles
  -------------------------------------------------------- */
  if ($ACCESS_MODE === 'pro' && !$is_pro_user): ?>
    <section class="pf-locked-all">
      <div class="pf-lock-box">
        <strong><?php esc_html_e('Pro workflow', 'prompt-finder'); ?></strong>
        <p class="pf-sub"><?php esc_html_e('Upgrade to unlock all steps of this workflow.', 'prompt-finder'); ?></p>
        <div class="pf-lock-actions">
          <a class="pf-btn pf-btn--primary" href="/pricing"><?php esc_html_e('View plans', 'prompt-finder'); ?></a>
          <a class="pf-btn" href="/login"><?php esc_html_e('Log in', 'prompt-finder'); ?></a>
        </div>
      </div>
    </section>
  <?php
    get_footer();
    return;
  endif;
  ?>

  <div class="pf-content">
    <?php if (!$has_steps): ?>
      <p class="pf-empty"><?php _e('No steps defined for this workflow yet.', 'prompt-finder'); ?></p>
    <?php else: ?>
      <ol class="pf-steps" id="pf-steps">
        <?php foreach ($steps as $i => $s):
          $idx        = $i + 1;
          $step_id    = $s['step_id_'] ?? '';
          $title      = $s['title'] ?? '';
          $objective  = $s['objective'] ?? '';
          $prompt     = $s['prompt'] ?? '';
          $vars       = (isset($s['variables']) && is_array($s['variables'])) ? $s['variables'] : [];
          $example    = $s['example_output'] ?? '';
          $checklist  = (isset($s['checklist']) && is_array($s['checklist'])) ? $s['checklist'] : [];
          $eta        = isset($s['estimated_time_min']) ? (int)$s['estimated_time_min'] : 0;
          
          // New ACF fields
          $checkpoint_required = !empty($s['checkpoint_required']);
          $checkpoint_message = $s['checkpoint_message'] ?? '';
          $selection_key = $s['selection_key'] ?? '';
          $context_requirements = (isset($s['context_requirements']) && is_array($s['context_requirements'])) ? $s['context_requirements'] : [];

          $needs_prev = (stripos(($prompt ?? ''), '{previous_output}') !== false);
          $step_anchor = 'step-'.$idx;

          // Lock-Entscheidung
          $locked = pf_step_is_locked($idx, $ACCESS_MODE, $USER_PLAN, (int)$FREE_STEP_LIMIT, (bool)$LOGIN_REQUIRED, (bool)$viewer_logged_in);

          $li_classes = 'pf-step pf-step-card' . ($locked ? ' pf-step--locked' : '') . ($checkpoint_required ? ' pf-step--checkpoint' : '');
        ?>
        <li class="<?php echo esc_attr($li_classes); ?>" id="<?php echo esc_attr($step_anchor); ?>">

          <div class="pf-step-meta">
            <?php /* optional ID anzeigen
            if ($step_id): ?><span class="pf-step-id"><?php echo esc_html__('ID', 'prompt-finder'); ?>: <?php echo esc_html($step_id); ?></span><?php endif;
            */ ?>
            <?php if (!empty($PF_FLAGS['lock_badges']) && $needs_prev && $idx > 1): ?>
              <span class="pf-badge"><?php printf(esc_html__('uses output from Step %d', 'prompt-finder'), $idx - 1); ?></span>
            <?php endif; ?>
          </div>

          <div class="pf-step-head">
            <h3 class="pf-step-title">
              <?php echo esc_html($title ?: __('Untitled', 'prompt-finder')); ?>
            </h3>
            <?php if ($eta): ?>
			  
			  
			  
              <span class="pf-step-time" title="Estimated time to complete this step">⏱ <?php echo (int)$eta; ?> min</span>
            <?php endif; ?>
          </div>
			<?php if ($locked): ?>
  <span class="pf-chip pf-chip--lock" title="<?php esc_attr_e('This step is locked', 'prompt-finder'); ?>">
    <?php echo esc_html($PF_COPY['chip_locked'] ?? 'Locked'); ?>
  </span>
<?php endif; ?>
			

          <?php if ($objective): ?>
            <p class="pf-sub"><?php echo nl2br(esc_html($objective)); ?></p>
          <?php endif; ?>

          <!-- Body-Wrapper nur für Lock-Blur -->
          <div class="<?php echo $locked ? 'pf-blur' : ''; ?>">

          <?php if (!empty($vars)): ?>
            <div class="pf-vars" aria-label="Variables">
              <?php if ($idx === 1): // einmalige, ausblendbare Mini-Hilfe oben bei Step 1 ?>
                <div class="pf-vars-hint pf-tile" data-vars-hint>
                  <strong>Customize:</strong> Fill the fields — the prompt updates live.
                  <button type="button" class="pf-hint-hide" data-action="hide-vars-hint">Don’t show again</button>
                </div>
              <?php endif; ?>

              <?php foreach ($vars as $v):
                $name     = trim($v['var_name'] ?? '');
                $label    = trim($v['var_label'] ?? '');
                $desc     = trim($v['var_description'] ?? '');
                $exampleV = trim($v['example_value'] ?? '');
                $required = !empty($v['required'] ?? $v['var_required'] ?? false);

                if (!$label && $name) {
                  $label = ucwords(str_replace(['_','-'], ' ', $name));
                }
                
                // Use example value as placeholder if available, otherwise use a generic placeholder
                $placeholder = $exampleV ?: 'Enter ' . strtolower($label ?: 'value');
              ?>
                <label class="pf-var <?php echo $required ? 'is-required' : ''; ?>">
                  <span class="pf-var-label">
                    <?php echo esc_html($label ?: 'Variable'); ?>
                    <?php if ($required): ?><span class="pf-req" title="Required">*</span><?php endif; ?>
                  </span>

                  <input type="text"
                         data-var-name="<?php echo esc_attr($name); ?>"
                         <?php if ($exampleV) echo 'data-example="'.esc_attr($exampleV).'"'; ?>
                         placeholder="<?php echo esc_attr($placeholder); ?>"
                         <?php if ($required): ?>aria-required="true"<?php endif; ?>>

                  <?php if ($desc): ?>
                    <small class="pf-var-help"><?php echo esc_html($desc); ?></small>
                  <?php endif; ?>
                </label>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>

          <label class="pf-prompt-label" for="<?php echo esc_attr('pf-prompt-'.$idx); ?>">
            <?php echo esc_html($PF_COPY['prompt_label'] ?? __('Prompt', 'prompt-finder')); ?>
          </label>
          <?php
            // Inject global context if context requirements exist
            $enhanced_prompt = $prompt;
            if (!empty($context_requirements) && function_exists('pf_inject_global_context')) {
              $enhanced_prompt = pf_inject_global_context($prompt, $context_requirements);
            }
          ?>
          <textarea id="<?php echo esc_attr('pf-prompt-'.$idx); ?>"
                    class="pf-prompt"
                    data-prompt-template
                    data-base="<?php echo esc_attr($enhanced_prompt); ?>"
                    data-original-base="<?php echo esc_attr($prompt); ?>"
                    rows="8"
                    spellcheck="false"><?php echo esc_html($enhanced_prompt); ?></textarea>

          <div class="pf-cta">
            <button class="pf-copy" data-action="copy-prompt">
              <?php echo esc_html($PF_COPY['copy_prompt'] ?? __('Copy prompt', 'prompt-finder')); ?>
            </button>
            <span class="pf-help-inline">→ <?php echo esc_html($PF_COPY['paste_hint'] ?? __('Paste into the same chat and run.', 'prompt-finder')); ?></span>
          </div>

          <?php if (!empty($example)): ?>
            <details class="pf-example">
              <summary><?php _e('Show example output', 'prompt-finder'); ?></summary>
              <pre><?php echo esc_html($example); ?></pre>
            </details>
          <?php endif; ?>

          <?php if (!empty($checklist)): ?>
            <div class="pf-checklist">
              <span class="pf-checklist-label"><?php _e('Checklist', 'prompt-finder'); ?></span>
              <ul class="pf-checklist-list">
                <?php foreach ($checklist as $c): ?>
                  <li><?php echo esc_html($c['check_item'] ?? ''); ?></li>
                <?php endforeach; ?>
              </ul>
            </div>
          <?php endif; ?>

          <?php if (!empty($context_requirements)): ?>
            <div class="pf-context-requirements">
              <strong><?php echo esc_html($PF_COPY['context_title'] ?? 'Context Requirements'); ?>:</strong>
              <?php foreach ($context_requirements as $req): ?>
                <div class="pf-context-item">
                  <span class="pf-context-type"><?php echo esc_html(ucfirst($req['context_type'] ?? '')); ?></span>
                  <span class="<?php echo !empty($req['required']) ? 'pf-context-required' : 'pf-context-optional'; ?>">
                    <?php echo !empty($req['required']) ? 'Required' : 'Optional'; ?>
                  </span>
                  <?php if (!empty($req['source'])): ?>
                    <span class="pf-context-source">(<?php echo esc_html(ucfirst(str_replace('_', ' ', $req['source']))); ?>)</span>
                  <?php endif; ?>
                </div>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>

          <?php if ($checkpoint_required && $checkpoint_message): ?>
            <div class="pf-checkpoint pf-tile" data-checkpoint="true">
              <div class="pf-checkpoint-message">
                <strong><?php echo esc_html($PF_COPY['checkpoint_title'] ?? 'Checkpoint'); ?>:</strong>
                <p><?php echo nl2br(esc_html($checkpoint_message)); ?></p>
              </div>
              <div class="pf-checkpoint-actions">
                <button class="pf-btn pf-btn--primary" data-action="continue-checkpoint">
                  <?php echo esc_html($PF_COPY['continue_button'] ?? 'Continue'); ?>
                </button>
              </div>
            </div>
          <?php endif; ?>

          <?php if ($idx < $total_steps): ?>
            <?php
              $next            = $steps[$i + 1] ?? null;
              $next_title      = $next['title'] ?? ('Step '.($idx+1));
              $next_prompt     = $next['prompt'] ?? '';
              $next_needs_prev = (stripos($next_prompt, '{previous_output}') !== false);
            ?>
            <?php if (!empty($PF_FLAGS['next_panel'])): ?>
              <div class="pf-next pf-tile" style="margin-top:.75rem;">
                <strong><?php echo esc_html($PF_COPY['up_next_title'] ?? 'Up next:'); ?></strong>
                <div class="pf-next-text pf-sub" style="margin:.25rem 0 .5rem;">
                  <?php if ($next_needs_prev): ?>
                    <?php
                      $txt = $PF_COPY['uses_prev_hint'] ?? 'Use this step’s output as {previous_output} in the next prompt.';
                      echo wp_kses_post($txt);
                    ?>
                  <?php else: ?>
                    <?php
                      $tpl  = $PF_COPY['continue_hint'] ?? 'Continue to <em>Step {n}: {title}</em>.';
                      $html = str_replace(['{n}','{title}'], [($idx+1), esc_html($next_title)], $tpl);
                      echo wp_kses_post($html);
                    ?>
                  <?php endif; ?>
                </div>
                <div class="pf-next-actions">
                  <a class="pf-btn" href="#<?php echo esc_attr('step-'.($idx+1)); ?>">
                    <?php
                      $btn = $PF_COPY['go_to_step'] ?? 'Go to Step {n}';
                      echo esc_html(str_replace('{n}', ($idx+1), $btn));
                    ?>
                  </a>
                </div>
              </div>
            <?php endif; ?>
          <?php else: ?>
            <div class="pf-next pf-tile" style="margin-top:.75rem;">
              <strong><?php echo esc_html($PF_COPY['done_title'] ?? 'Done:'); ?></strong>
              <div class="pf-next-text pf-sub" style="margin:.25rem 0 0;">
                <?php echo esc_html($PF_COPY['done_text'] ?? 'You’ve completed all steps. Review the result and save it to your process/tool.'); ?>
              </div>
            </div>
          <?php endif; ?>

          </div><!-- /pf-blur wrapper -->

          <?php if ($locked): ?>
            <div class="pf-step-cta">
              <?php if ($ACCESS_MODE === 'half_locked' && !$viewer_logged_in): ?>
                <p class="pf-sub"><?php esc_html_e('Create a free account to continue with this step.', 'prompt-finder'); ?></p>
                <a class="pf-btn pf-btn--primary" href="/login"><?php esc_html_e('Log in / Sign up', 'prompt-finder'); ?></a>
              <?php else: ?>
                <p class="pf-sub"><?php esc_html_e('Unlock to continue this workflow.', 'prompt-finder'); ?></p>
                <a class="pf-btn pf-btn--primary" href="/pricing"><?php esc_html_e('Upgrade to Pro', 'prompt-finder'); ?></a>
              <?php endif; ?>
            </div>
          <?php endif; ?>

        </li>
        <?php endforeach; ?>
      </ol>
    <?php endif; ?>
  </div>

  <?php if (!empty($PF_CONFIG['layout']['three_grid_under_steps'])): ?>
    <div class="pf-3grid" style="margin-top:18px;">
      <?php if (!empty($PF_FLAGS['share'])): ?>
        <section class="pf-share pf-tile">
          <strong><?php echo esc_html($PF_COPY['share_title'] ?? 'Share'); ?>:</strong>
          <a href="#" class="pf-share-btn" data-action="copy-link"><?php echo esc_html($PF_COPY['copy_link'] ?? 'Copy link'); ?></a>
        </section>
      <?php endif; ?>

      <?php if (!empty($PF_FLAGS['rating'])): ?>
        <?php
          $post_id = get_the_ID();
          $sum     = (int) get_post_meta($post_id, 'pf_rating_sum', true);
          $count   = (int) get_post_meta($post_id, 'pf_rating_count', true);
          $avg     = $count ? round($sum / $count, 1) : 0;
        ?>
        <section class="pf-rating pf-tile"
                 data-post-id="<?php echo esc_attr($post_id); ?>"
                 data-avg="<?php echo esc_attr($avg); ?>"
                 data-count="<?php echo esc_attr($count); ?>">
          <p class="pf-rating-title">
            <?php echo esc_html($PF_COPY['rating_title'] ?? 'How helpful was this workflow?'); ?>
          </p>

          <div class="pf-stars" role="radiogroup" aria-label="<?php esc_attr_e('Rate this workflow', 'prompt-finder'); ?>">
            <button class="pf-star" data-value="1" role="radio" aria-checked="false" title="<?php esc_attr_e('1 = Not helpful','prompt-finder'); ?>"></button>
            <button class="pf-star" data-value="2" role="radio" aria-checked="false" title="<?php esc_attr_e('2 = Needs work','prompt-finder'); ?>"></button>
            <button class="pf-star" data-value="3" role="radio" aria-checked="false" title="<?php esc_attr_e('3 = Okay','prompt-finder'); ?>"></button>
            <button class="pf-star" data-value="4" role="radio" aria-checked="false" title="<?php esc_attr_e('4 = Helpful','prompt-finder'); ?>"></button>
            <button class="pf-star" data-value="5" role="radio" aria-checked="false" title="<?php esc_attr_e('5 = Excellent','prompt-finder'); ?>"></button>
          </div>

          <div class="pf-rating-meta">
            <span class="pf-rating-avg"><?php echo $avg ? esc_html($avg) : '–'; ?></span>
            <span class="pf-rating-count">(<?php echo (int)$count; ?>)</span>
            <span class="pf-rating-msg pf-sub"><?php echo esc_html($PF_COPY['rating_hint'] ?? 'Click a star to rate'); ?></span>
          </div>
        </section>
      <?php endif; ?>

      <?php /* TIP-BOX ENTFERNT */ ?>
    </div>
  <?php endif; ?>
</div>

<?php get_footer(); ?>
