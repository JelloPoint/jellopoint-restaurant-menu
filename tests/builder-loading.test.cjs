const fs = require('node:fs');
const vm = require('node:vm');
const assert = require('node:assert/strict');
const path = require('node:path');
const root = process.argv[2] ? path.resolve(process.argv[2]) : path.join(__dirname, '..');
const freePackage = process.argv[3] === 'free';
const source = fs.readFileSync(path.join(root, 'includes/admin/assets/jprm-menu-builder.js'), 'utf8');
const elements = new Map(), requests = [], timers = new Map();
let timerId = 0;
function $(selector) {
  if (!elements.has(selector)) {
    const attributes = {};
    const element = {
      visible: false, attributes,
      attr(k, v) { if (v === undefined) return attributes[k]; attributes[k] = v; return this; },
      removeAttr(k) { delete attributes[k]; return this; },
      addClass() { return this; }, removeClass() { return this; },
      show() { this.visible = true; return this; }, hide() { this.visible = false; return this; },
      text(v) { this.value = v; return this; }, on() { return this; },
      each() { return this; }
    };
    elements.set(selector, element);
  }
  return elements.get(selector);
}
$.ajax = options => {
  const failure = [], complete = [];
  const request = { options,
    fail(fn) { failure.push(fn); return this; },
    done() { return this; },
    always(fn) { complete.push(fn); return this; },
    settle(error) { if (error) failure.forEach(fn => fn(error)); complete.forEach(fn => fn()); }
  };
  requests.push(request); return request;
};
const context = {jQuery:$, document:{}, JPRM_MENU_BUILDER:{root:'/api',labels:{loading:'Loading',saving:'Saving'}},
  setTimeout(fn) { timers.set(++timerId, fn); return timerId; }, clearTimeout(id) { timers.delete(id); }};
vm.runInNewContext(source.replace('})(jQuery);', 'globalThis.builderTest = {apiGet, apiPost, state, loadInfoBlocks}; })(jQuery);'), context);
const api = context.builderTest;
const flushTimers = () => { for (const fn of timers.values()) fn(); timers.clear(); };
api.state.ready = true;
api.apiGet('one'); api.apiGet('two');
assert.equal($('#jprm-loading').visible, true);
assert.equal($('.jprm-toolbar, .jprm-columns').attributes.inert, '');
requests[0].settle(); flushTimers();
assert.equal($('#jprm-loading').visible, true, 'First completion hid another pending request');
requests[1].settle();
api.apiPost('save', {}); // Follow-up request cancels the pending hide.
flushTimers();
assert.equal($('#jprm-loading').visible, true);
assert.equal($('#jprm-loading-text').value, 'Saving');
requests[2].settle({responseJSON:{message:'Failed'}}); flushTimers();
assert.equal($('#jprm-loading').visible, false);
assert.equal($('.jprm-toolbar, .jprm-columns').attributes.inert, undefined);
assert.equal($('.jprm-menu-builder-wrap').attributes['aria-busy'], 'false');
$.Deferred = () => { const result = {resolve() { return result; }, promise() { return result; }}; return result; };
api.state.currentMenu = 42;
api.state.infoBlocks = [{id: 1}]; api.state.infoPlacements = [{id: 1}];
const before = requests.length;
context.JPRM_MENU_BUILDER.can_print = false;
assert.doesNotThrow(() => api.loadInfoBlocks());
assert.equal(requests.length, before, 'Free Builder called a Pro-only endpoint');
if (!freePackage) assert.equal(api.state.infoPlacements.length, 0, 'Stale Pro placements retained in UI');
context.JPRM_MENU_BUILDER.can_print = true;
api.loadInfoBlocks();
if (freePackage) {
  assert.equal(requests.length, before, 'Free artifact must not contain a Pro request even with a forged flag');
  assert.doesNotMatch(source, /menu-builder\/info-blocks/);
} else {
  assert.equal(requests.length, before + 1, 'Pro Builder did not load placements');
  assert.match(requests.at(-1).options.url, /info-blocks\?menu_id=42/);
}
console.log('Builder loading lifecycle and Free/Pro request checks passed.');
