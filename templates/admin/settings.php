<?php
$t = fn(string $k, array $p = []) => \Wiki\Services\I18nService::t($k, $p);
$all = \Wiki\Models\Settings::all();
$defaults = \Wiki\Models\Settings::defaults();

$skipPrefixes = ['mail.tpl.'];

$groups = [
    'site'       => ['label' => 'admin.group.site',       'prefix' => 'site.'],
    'seo'        => ['label' => 'admin.group.seo',        'prefix' => 'seo.'],
    'mail'       => ['label' => 'admin.group.mail',       'prefix' => 'mail.'],
    'auth'       => ['label' => 'admin.group.auth',       'prefix' => 'auth.'],
    'feature'    => ['label' => 'admin.group.feature',    'prefix' => 'feature.'],
    'storage'    => ['label' => 'admin.group.storage',    'prefix' => 'storage.'],
    'attachment' => ['label' => 'admin.group.attachment', 'prefix' => 'attachment.'],
    'search'     => ['label' => 'admin.group.search',     'prefix' => 'search.'],
    'ui'         => ['label' => 'admin.group.ui',         'prefix' => 'ui.'],
];

$defaultTab = (string)($_GET['tab'] ?? 'site');
if (!isset($groups[$defaultTab])) $defaultTab = 'site';

$typeMap = [
    'site.timezone'             => 'timezone',
    'site.default_lang'         => 'content_lang',
    'seo.robots'                => 'choices:index,follow|noindex,nofollow|index,nofollow|noindex,follow',
    'mail.transport'            => 'choices:smtp|sendmail|mail',
    'mail.encryption'           => 'choices:ssl|tls|none',
    'mail.password'             => 'password',
    'auth.default_role'         => 'choices:user|editor',
    'auth.github_client_secret' => 'password',
    'storage.cache'             => 'choices:none|redis',
    'storage.redis_password'    => 'password',
    'attachment.driver'         => 'choices:local|s3',
    'attachment.s3_secret_key'  => 'password',
    'search.mode'               => 'choices:simple|fts',
    'ui.dark_mode'              => 'choices:auto|light|dark',
    'site.footer'               => 'textarea',
    'seo.404_content'           => 'textarea',
    'ui.custom_css'             => 'textarea',
    'ui.custom_js'              => 'textarea',
];

/**
 * Conditional visibility. Each field lists (controller-key => allowed-values).
 * A field renders only when every controller holds one of the allowed values.
 * Values are compared as strings, so booleans use "0" or "1".
 */
$dependsMap = [
    // Site
    'site.maintenance_ips'      => ['site.maintenance' => ['1']],
    'site.logo'                 => [],
    'site.favicon'              => [],

    // SEO
    'seo.404_page_slug'         => ['seo.404_content' => ['']],
    'seo.404_content'           => [],

    // Mail: everything depends on the master switch; SMTP-only fields depend on transport
    'mail.transport'            => ['mail.enabled' => ['1']],
    'mail.host'                 => ['mail.enabled' => ['1'], 'mail.transport' => ['smtp']],
    'mail.port'                 => ['mail.enabled' => ['1'], 'mail.transport' => ['smtp']],
    'mail.encryption'           => ['mail.enabled' => ['1'], 'mail.transport' => ['smtp']],
    'mail.username'             => ['mail.enabled' => ['1'], 'mail.transport' => ['smtp']],
    'mail.password'             => ['mail.enabled' => ['1'], 'mail.transport' => ['smtp']],
    'mail.from_address'         => ['mail.enabled' => ['1']],
    'mail.from_name'            => ['mail.enabled' => ['1']],

    // Auth
    'auth.invite_only'          => ['auth.allow_register' => ['1']],
    'auth.email_verify'         => ['auth.allow_register' => ['1']],
    'auth.register_review'      => ['auth.allow_register' => ['1']],
    'auth.default_role'         => ['auth.allow_register' => ['1']],
    'auth.github_client_id'     => ['auth.github_oauth' => ['1']],
    'auth.github_client_secret' => ['auth.github_oauth' => ['1']],

    // Features
    'feature.fuzzy_search'      => ['feature.search' => ['1']],
    'feature.comments'          => ['feature.issues' => ['1']],

    // Storage
    'storage.redis_host'        => ['storage.cache' => ['redis']],
    'storage.redis_port'        => ['storage.cache' => ['redis']],
    'storage.redis_password'    => ['storage.cache' => ['redis']],
    'storage.redis_db'          => ['storage.cache' => ['redis']],

    // Attachments
    'attachment.allowed_types'  => ['feature.attachments' => ['1']],
    'attachment.max_size'       => ['feature.attachments' => ['1']],
    'attachment.driver'         => ['feature.attachments' => ['1']],
    'attachment.s3_endpoint'    => ['feature.attachments' => ['1'], 'attachment.driver' => ['s3']],
    'attachment.s3_bucket'      => ['feature.attachments' => ['1'], 'attachment.driver' => ['s3']],
    'attachment.s3_region'      => ['feature.attachments' => ['1'], 'attachment.driver' => ['s3']],
    'attachment.s3_access_key'  => ['feature.attachments' => ['1'], 'attachment.driver' => ['s3']],
    'attachment.s3_secret_key'  => ['feature.attachments' => ['1'], 'attachment.driver' => ['s3']],
    'attachment.s3_public_url'  => ['feature.attachments' => ['1'], 'attachment.driver' => ['s3']],

    // Search
    'search.mode'               => ['feature.search' => ['1']],

    // UI
    'ui.theme_color'            => [],
];

/**
 * Warnings shown at the top of the form when a dangerous combination is
 * detected. Each entry is [callable, translation key].
 */
$warnings = [];
$checkWarnings = function () use (&$warnings) {
    $s = fn($k) => \Wiki\Models\Settings::getBool($k);

    if ($s('auth.email_verify') && !$s('mail.enabled')) {
        $warnings[] = 'warning.email_verify_without_mail';
    }
    if ($s('auth.allow_register') && !$s('auth.invite_only') && !$s('auth.email_verify') && !$s('auth.register_review')) {
        $warnings[] = 'warning.open_registration_no_protection';
    }
    if ($s('feature.api_read') && !$s('feature.anonymous_read')) {
        $warnings[] = 'warning.api_read_but_anonymous_off';
    }
    if (\Wiki\Models\Settings::get('storage.cache', 'none') === 'redis'
        && !class_exists('Redis')) {
        $warnings[] = 'warning.redis_extension_missing';
    }
    if (\Wiki\Models\Settings::get('attachment.driver', 'local') === 's3'
        && !extension_loaded('curl')) {
        $warnings[] = 'warning.s3_without_curl';
    }
};
$checkWarnings();

function choiceLabel(string $value): string
{
    $k = 'settings.value.' . $value;
    $v = \Wiki\Services\I18nService::t($k);
    return $v === $k ? $value : $v;
}

function render_timezone_select(string $name, string $current): string
{
    $byRegion = [];
    foreach (timezone_identifiers_list() as $z) {
        $parts = explode('/', $z, 2);
        $region = count($parts) > 1 ? $parts[0] : 'Other';
        $byRegion[$region][] = $z;
    }
    ksort($byRegion);

    $html = '<select name="' . e($name) . '" class="mt-1 w-full border rounded px-3 py-2">';
    foreach ($byRegion as $region => $list) {
        $html .= '<optgroup label="' . e($region) . '">';
        foreach ($list as $z) {
            $sel = $z === $current ? ' selected' : '';
            $html .= '<option value="' . e($z) . '"' . $sel . '>' . e($z) . '</option>';
        }
        $html .= '</optgroup>';
    }
    return $html . '</select>';
}

function render_content_lang_select(string $name, string $current): string
{
    $langs = [
        'en-US' => 'English (US)', 'zh-CN' => '中文（简体）', 'zh-TW' => '中文（繁體）',
        'ja-JP' => '日本語', 'ko-KR' => '한국어', 'fr-FR' => 'Français', 'de-DE' => 'Deutsch',
        'es-ES' => 'Español', 'ru-RU' => 'Русский', 'pt-BR' => 'Português (Brasil)',
        'it-IT' => 'Italiano', 'ar-SA' => 'العربية', 'hi-IN' => 'हिन्दी',
        'nl-NL' => 'Nederlands', 'pl-PL' => 'Polski', 'tr-TR' => 'Türkçe',
        'vi-VN' => 'Tiếng Việt', 'th-TH' => 'ไทย', 'id-ID' => 'Bahasa Indonesia',
        'sv-SE' => 'Svenska', 'cs-CZ' => 'Čeština', 'el-GR' => 'Ελληνικά',
        'he-IL' => 'עברית', 'uk-UA' => 'Українська',
    ];
    if (!isset($langs[$current])) $langs[$current] = $current;

    $html = '<select name="' . e($name) . '" class="mt-1 w-full border rounded px-3 py-2">';
    foreach ($langs as $code => $label) {
        $sel = $code === $current ? ' selected' : '';
        $html .= '<option value="' . e($code) . '"' . $sel . '>' . e($label) . ' (' . e($code) . ')</option>';
    }
    return $html . '</select>';
}

function is_skipped(string $key, array $prefixes): bool
{
    foreach ($prefixes as $p) {
        if (str_starts_with($key, $p)) return true;
    }
    return false;
}

if (!empty($saveMsg)) {
    $cls = $saveMsg['type'] === 'success'
        ? 'bg-green-50 text-green-700 border-green-200'
        : 'bg-red-50 text-red-700 border-red-200';
    echo '<div class="border rounded p-3 mb-4 ' . $cls . '">' . e($saveMsg['text']) . '</div>';
}
?>

<?php if (!empty($_GET['rebuilt'])): ?>
  <div class="bg-green-50 text-green-700 border border-green-200 rounded p-3 mb-4">
    <?= e($t('admin.search.rebuild_done', ['n' => (int)$_GET['rebuilt']])) ?>
  </div>
<?php endif; ?>

<?php if ($warnings): ?>
  <div class="space-y-2 mb-6">
    <?php foreach ($warnings as $w): ?>
      <div class="bg-amber-50 border border-amber-200 text-amber-800 text-sm rounded p-3 flex items-start gap-2">
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor"
             stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-4 h-4 mt-0.5 shrink-0">
          <path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/>
          <line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/>
        </svg>
        <span><?= e($t($w)) ?></span>
      </div>
    <?php endforeach; ?>
  </div>
<?php endif; ?>

<!-- Tab navigation -->
<div class="bg-white rounded shadow mb-4 border-b border-slate-200">
  <nav class="flex gap-1 overflow-x-auto px-2 py-1" id="settings-tabs">
    <?php foreach ($groups as $gk => $g):
        $active = $gk === $defaultTab;
    ?>
      <button type="button" data-tab="<?= e($gk) ?>"
              class="tab-btn whitespace-nowrap text-sm px-3 py-2 rounded-t border-b-2 transition-colors
                     <?= $active
                        ? 'tab-active border-blue-600 text-blue-700 font-medium bg-blue-50'
                        : 'border-transparent text-slate-500 hover:text-slate-800 hover:bg-slate-50' ?>">
        <?= e($t($g['label'])) ?>
      </button>
    <?php endforeach; ?>
  </nav>
</div>

<form method="post" action="<?= e(url('admin/settings')) ?>" class="space-y-6">
  <input type="hidden" name="csrf" value="<?= e(\Wiki\Auth\Auth::csrf()) ?>">

  <?php foreach ($groups as $gk => $g):
      $keys = array_filter(
          array_keys($defaults),
          fn($k) => str_starts_with($k, $g['prefix']) && !is_skipped($k, $skipPrefixes)
      );
      if (!$keys) continue;
      $isActive = $gk === $defaultTab;
  ?>
    <section class="tab-panel <?= $isActive ? '' : 'hidden' ?>" data-tab-panel="<?= e($gk) ?>">
      <div class="bg-white rounded shadow p-6">
        <div class="space-y-5">
          <?php foreach ($keys as $k):
              $val = $all[$k] ?? $defaults[$k];
              $isBool = $defaults[$k] === '0' || $defaults[$k] === '1';

              $labelKey = 'settings.label.' . $k;
              $label = $t($labelKey);
              if ($label === $labelKey) $label = $k;

              $hintKey = 'settings.hint.' . $k;
              $hint = $t($hintKey);
              if ($hint === $hintKey) $hint = '';

              $type = $typeMap[$k] ?? ($isBool ? 'bool' : 'text');
              $depends = $dependsMap[$k] ?? null;
              $dependsAttr = $depends ? ' data-depends="' . e(json_encode($depends)) . '"' : '';
          ?>
            <div class="settings-field" data-field-key="<?= e($k) ?>"<?= $dependsAttr ?>>
              <?php if ($type === 'bool'): ?>
                <label class="flex items-start gap-3 cursor-pointer">
                  <input type="hidden" name="settings[<?= e($k) ?>]" value="0">
                  <input type="checkbox" name="settings[<?= e($k) ?>]" value="1"
                         class="mt-1" <?= $val === '1' ? 'checked' : '' ?>>
                  <div class="flex-1">
                    <div class="text-sm font-medium text-slate-700"><?= e($label) ?></div>
                    <?php if ($hint !== ''): ?>
                      <div class="text-xs text-slate-400 mt-0.5"><?= e($hint) ?></div>
                    <?php endif; ?>
                  </div>
                </label>
              <?php else: ?>
                <label class="block">
                  <span class="text-sm font-medium text-slate-700"><?= e($label) ?></span>
                  <?php if ($hint !== ''): ?>
                    <span class="text-xs text-slate-400 block mt-0.5"><?= e($hint) ?></span>
                  <?php endif; ?>

                  <?php if ($type === 'timezone'): ?>
                    <?= render_timezone_select('settings[' . $k . ']', (string)$val) ?>
                  <?php elseif ($type === 'content_lang'): ?>
                    <?= render_content_lang_select('settings[' . $k . ']', (string)$val) ?>
                  <?php elseif (str_starts_with($type, 'choices:')): ?>
                    <?php $choices = explode('|', substr($type, 8)); ?>
                    <select name="settings[<?= e($k) ?>]" class="mt-1 w-full border rounded px-3 py-2">
                      <?php foreach ($choices as $c): ?>
                        <option value="<?= e($c) ?>" <?= (string)$val === $c ? 'selected' : '' ?>>
                          <?= e(choiceLabel($c)) ?>
                        </option>
                      <?php endforeach; ?>
                    </select>
                  <?php elseif ($type === 'password'): ?>
                    <input type="password" name="settings[<?= e($k) ?>]"
                           value="<?= e((string)$val) ?>"
                           autocomplete="new-password"
                           class="mt-1 w-full border rounded px-3 py-2 font-mono text-sm">
                  <?php elseif ($type === 'textarea'): ?>
                    <textarea name="settings[<?= e($k) ?>]" rows="5"
                              class="mt-1 w-full border rounded px-3 py-2 font-mono text-sm"><?= e((string)$val) ?></textarea>
                  <?php else: ?>
                    <input name="settings[<?= e($k) ?>]" value="<?= e((string)$val) ?>"
                           class="mt-1 w-full border rounded px-3 py-2">
                  <?php endif; ?>
                </label>
              <?php endif; ?>
            </div>
          <?php endforeach; ?>
        </div>

        <?php if ($g['prefix'] === 'search.'): ?>
          <div class="mt-6 pt-4 border-t flex items-center gap-3 settings-field"
               data-depends='{"feature.search":["1"]}'>
            <a href="<?= e(url('admin/settings/rebuild')) ?>"
               class="text-sm bg-slate-700 text-white px-3 py-1.5 rounded hover:bg-slate-800">
              <?= e($t('admin.search.rebuild')) ?>
            </a>
            <span class="text-xs text-slate-500"><?= e($t('admin.search.rebuild_hint')) ?></span>
          </div>
        <?php endif; ?>

        <?php if ($g['prefix'] === 'attachment.'): ?>
          <div class="mt-6 pt-4 border-t flex items-center gap-3">
            <button type="button" id="test-s3-btn"
                    class="text-sm bg-slate-700 text-white px-3 py-1.5 rounded hover:bg-slate-800">
              <?= e($t('admin.attachment.test_s3')) ?>
            </button>
            <span id="test-s3-msg" class="text-xs text-slate-500"></span>
          </div>
        <?php endif; ?>
      </div>
    </section>
  <?php endforeach; ?>

  <div class="sticky bottom-4 z-10">
    <button class="bg-blue-600 text-white px-6 py-2 rounded shadow hover:bg-blue-700">
      <?= e($t('admin.save_all')) ?>
    </button>
  </div>
</form>

<script>
(function () {
  // ---------- Tab switching ----------
  const tabs   = document.querySelectorAll('.tab-btn');
  const panels = document.querySelectorAll('.tab-panel');

  function selectTab(name, push) {
    tabs.forEach(b => {
      const on = b.dataset.tab === name;
      b.classList.toggle('tab-active', on);
      b.classList.toggle('border-blue-600', on);
      b.classList.toggle('text-blue-700', on);
      b.classList.toggle('font-medium', on);
      b.classList.toggle('bg-blue-50', on);
      b.classList.toggle('border-transparent', !on);
      b.classList.toggle('text-slate-500', !on);
    });
    panels.forEach(p => p.classList.toggle('hidden', p.dataset.tabPanel !== name));
    if (push) history.replaceState(null, '', '#tab-' + name);
  }

  tabs.forEach(b => b.addEventListener('click', () => selectTab(b.dataset.tab, true)));

  const hash = location.hash.match(/^#tab-(\w+)$/);
  if (hash && document.querySelector('[data-tab-panel="' + hash[1] + '"]')) {
    selectTab(hash[1], false);
  }

  // ---------- Conditional visibility ----------
  const fields = document.querySelectorAll('.settings-field[data-depends]');

  function readValue(key) {
    const name = 'settings[' + key + ']';
    const cb = document.querySelector('input[type="checkbox"][name="' + name + '"]');
    if (cb) return cb.checked ? '1' : '0';
    const el = document.querySelector(
      'select[name="' + name + '"], input[name="' + name + '"], textarea[name="' + name + '"]'
    );
    return el ? String(el.value) : null;
  }

  function evaluate() {
    fields.forEach(function (field) {
      let deps;
      try { deps = JSON.parse(field.dataset.depends || '{}'); } catch (e) { return; }

      let visible = true;
      for (const key in deps) {
        const current = readValue(key);
        if (current === null) continue;
        const allowed = deps[key];
        if (!Array.isArray(allowed) || allowed.indexOf(current) === -1) {
          visible = false;
          break;
        }
      }

      field.style.display = visible ? '' : 'none';
    });
  }

  document.querySelectorAll('[name^="settings["]').forEach(function (el) {
    el.addEventListener('change', evaluate);
    el.addEventListener('input', evaluate);
  });

  evaluate();
})();

document.getElementById('test-s3-btn')?.addEventListener('click', async () => {
  const btn = document.getElementById('test-s3-btn');
  const msg = document.getElementById('test-s3-msg');
  btn.disabled = true;
  msg.textContent = '...';
  const fd = new FormData();
  fd.append('csrf', <?= json_encode(\Wiki\Auth\Auth::csrf()) ?>);
  try {
    const res = await fetch('<?= e(url('api/test-s3')) ?>', { method: 'POST', body: fd, credentials: 'same-origin' });
    const json = await res.json();
    msg.textContent = json.message || (json.ok ? 'OK' : 'Failed');
    msg.className = 'text-xs ' + (json.ok ? 'text-green-600' : 'text-red-600');
  } catch (e) {
    msg.textContent = String(e);
    msg.className = 'text-xs text-red-600';
  } finally {
    btn.disabled = false;
  }
});
</script>
