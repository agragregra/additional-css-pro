<?php
/**
 * Plugin Name: Additional CSS Pro
 * Description: Replace the default Additional CSS editor in WordPress Site Editor with a powerful Ace code editor featuring syntax highlighting, auto-completion, Emmet, Search and detachable window.
 * Version: 1.0
 * Author: WebDesign Master
 */

if (!defined('ABSPATH')) exit;

$selector = '.edit-site-global-styles-screen-css';

add_action('admin_enqueue_scripts', function($hook) use ($selector) {
    if ($hook !== 'site-editor.php') return;

    $ace_url = plugin_dir_url(__FILE__) . 'vendor/ace';
    wp_enqueue_script('emmet-core-lib', $ace_url . '/emmet.js', [], null, true);
    wp_enqueue_script('ace-core', $ace_url . '/ace.min.js', ['emmet-core-lib'], '1.32.3', true);

    wp_localize_script('ace-core', 'aceParams', [
        'base' => esc_url_raw($ace_url),
        'selector' => esc_attr($selector . ' textarea')
    ]);

    wp_add_inline_script('ace-core', "
        (function() {
            let loading = false;
            const init = () => {
                if (loading) return;
                const el = document.querySelector(aceParams.selector);
                if (!el || el.dataset.aceActive || typeof ace === 'undefined') return;

                loading = true;
                el.dataset.aceActive = 'true';

                const win = document.createElement('div');
                win.id = 'ace-window-container';
                
                const header = document.createElement('div');
                header.className = 'ace-window-header';
                header.innerHTML = '<span class=\"ace-win-title\">CSS Editor</span><button class=\"ace-detach-toggle\">Detach</button>';
                
                const div = document.createElement('div');
                div.id = 'ace-pro-editor';
                
                win.appendChild(header);
                win.appendChild(div);
                el.parentNode.insertBefore(win, el.nextSibling);
                el.style.display = 'none';

                ace.config.set('basePath', aceParams.base);
                ace.config.set('suffix', '.min.js');

                const ed = ace.edit(div);
                ed.getSession().setMode('ace/mode/css');
                
                ed.setTheme('ace/theme/one_dark');

                ed.setOptions({
                    fontSize: '16px',
                    fontFamily: '\"SF Mono\", \"Cascadia Code\", \"Fira Code\", \"JetBrains Mono\", monospace',
                    useSoftTabs: true,
                    tabSize: 2
                });
                div.style.lineHeight = '1.5';
                ed.setValue(el.value, -1);

                ed.getSession().getUndoManager().reset(); 

                ace.config.loadModule('ace/ext/searchbox');
                ace.config.loadModule('ace/ext/language_tools', () => {
                    ed.setOptions({enableBasicAutocompletion:true, enableLiveAutocompletion:true, enableSnippets:true});
                    if (ed.completer) ed.completer.keyboardHandler.bindKey('Tab', null);
                });
                ace.config.loadModule('ace/ext/emmet', () => {
                    const check = setInterval(() => {
                        if (typeof window.emmet !== 'undefined') {
                            clearInterval(check);
                            const p = () => window.emmet;
                            ace.define('ace/emmet-core', [], p);
                            ace.define('emmet', [], p);
                            ed.setOption('enableEmmet', true);
                        }
                    }, 100);
                });

                const toggleBtn = header.querySelector('.ace-detach-toggle');
                let parentNode = win.parentNode;
                let nextSibling = win.nextSibling;

                toggleBtn.onclick = (e) => {
                    e.preventDefault();
                    if (win.classList.toggle('is-detached')) {
                        document.body.appendChild(win);
                        toggleBtn.innerText = 'Attach';
                        win.style.right = '20px'; 
                        win.style.bottom = '20px';
                        win.style.width = '700px';
                        win.style.height = '400px';
                        win.style.borderRadius = '8px';
                    } else {
                        ['position', 'left', 'top', 'width', 'height', 'zIndex', 'borderRadius'].forEach(p => win.style[p] = '');
                        parentNode.insertBefore(win, nextSibling);
                        toggleBtn.innerText = 'Detach';
                    }
                    setTimeout(() => ed.resize(), 30);
                };

                header.onmousedown = (e) => {
                    if (!win.classList.contains('is-detached') || e.target === toggleBtn) return;
                    let rect = win.getBoundingClientRect();
                    let shiftX = e.clientX - rect.left, shiftY = e.clientY - rect.top;
                    const move = (e) => {
                        win.style.left = e.clientX - shiftX + 'px';
                        win.style.top = e.clientY - shiftY + 'px';
                    };
                    document.addEventListener('mousemove', move);
                    document.onmouseup = () => document.removeEventListener('mousemove', move);
                };

                const setter = Object.getOwnPropertyDescriptor(window.HTMLTextAreaElement.prototype, 'value').set;
                ed.getSession().on('change', () => {
                    setter.call(el, ed.getValue());
                    el.dispatchEvent(new Event('input', { bubbles: true }));
                });

                new ResizeObserver(() => ed.resize()).observe(win);
                loading = false;
            };

            let t;
            new MutationObserver(() => { clearTimeout(t); t = setTimeout(init, 250); }).observe(document.body, { childList: true, subtree: true });
        })();
    ");
});

add_action('admin_head', function() use ($selector) {
    echo '<style>
        #ace-window-container { 
            display: flex; flex-direction: column; 
            margin: 10px 0; background: #282c34; 
            height: 650px; box-sizing: border-box; 
            overflow: hidden;
        }
        .ace-window-header { 
            display: flex; background: #21252b; padding: 8px 12px; 
            border-bottom: 1px solid #181a1f;
            justify-content: space-between; align-items: center; flex-shrink: 0;
        }
        .ace-win-title { font-size: 11px; font-weight: 600; text-transform: uppercase; color: #abb2bf; letter-spacing: 0.5px; }
        .ace-detach-toggle { 
            cursor: pointer; padding: 3px 10px; font-size: 10px; 
            border: 1px solid #3e4451; background: #2c313a; color: #abb2bf;
            border-radius: 4px; transition: all 0.2s;
        }
        .ace-detach-toggle:hover { background: #3e4451; color: #fff; }
        
        #ace-pro-editor { flex-grow: 1; width: 100% !important; position: relative !important; }

        /* ТЕМНЫЙ СКРОЛЛБАР */
        #ace-pro-editor ::-webkit-scrollbar { width: 12px; height: 12px; background-color: #21252b; }
        #ace-pro-editor ::-webkit-scrollbar-track { background-color: #21252b; }
        #ace-pro-editor ::-webkit-scrollbar-thumb { 
            background-color: #3e4451; border: 3px solid #21252b; border-radius: 10px; 
        }
        #ace-pro-editor ::-webkit-scrollbar-thumb:hover { background-color: #4b5263; }
        #ace-pro-editor ::-webkit-scrollbar-corner { background-color: #21252b; }

        #ace-window-container.is-detached {
            position: fixed !important; z-index: 999999 !important;
            box-shadow: 0 20px 50px rgba(0,0,0,0.5);
            resize: both; margin: 0;
            border: 1px solid #181a1f;
        }
        #ace-window-container.is-detached .ace-window-header { cursor: move; }

        ' . $selector . ' .components-base-control__field { display: block !important; }
        
        .ace_search { background: #21252b !important; color: #abb2bf !important; border: 1px solid #181a1f !important; box-shadow: 0 5px 15px rgba(0,0,0,0.5) !important; }
        .ace_search_field { background: #282c34 !important; border: 1px solid #3e4451 !important; color: #abb2bf !important; }
        .ace_searchbtn, .ace_button { background: #3e4451 !important; color: #abb2bf !important; }
    </style>';
});
