<script setup>
import { computed, onMounted, ref } from 'vue';

const root = document.querySelector('#app');
const user = JSON.parse(root?.dataset.user || '{}');
const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;
const analytics = ref(null);
const sites = ref([]);
const leads = ref([]);
const loading = ref(true);
const error = ref('');
const notice = ref('');
const activeTab = ref('Overview');
const search = ref('');
const leadSite = ref('');
const leadPage = ref(1);
const leadPagination = ref({ current_page: 1, last_page: 1, total: 0 });
const days = ref(30);
const siteForm = ref({ name: '', domain: '' });
const tokenModal = ref('');
const selectedLead = ref(null);
const chartWidth = 720;
const chartHeight = 220;

const chartPoints = computed(() => {
  if (!analytics.value?.daily?.length) return { views: '', leads: '', labels: [] };
  const rows = analytics.value.daily;
  const max = Math.max(1, ...rows.map((row) => Number(row.page_views)));
  const x = (index) => 12 + index * ((chartWidth - 24) / Math.max(rows.length - 1, 1));
  const y = (value) => chartHeight - 22 - (Number(value) / max) * (chartHeight - 46);
  return {
    views: rows.map((row, i) => `${x(i)},${y(row.page_views)}`).join(' '),
    leads: rows.map((row, i) => `${x(i)},${y(Number(row.leads) * (max / Math.max(1, ...rows.map((item) => Number(item.leads)))))}`).join(' '),
    labels: rows.filter((_, i) => i === 0 || i === rows.length - 1 || i === Math.floor(rows.length / 2)),
  };
});

async function request(url, options) {
  const response = await window.pulseFetch(url, options);
  const body = await response.json().catch(() => ({}));
  if (!response.ok) {
    const message = body.message || Object.values(body.errors || {})[0]?.[0] || 'The request could not be completed.';
    throw new Error(message);
  }
  return body.data;
}

async function loadAnalytics() {
  analytics.value = await request(`/dashboard/analytics?days=${days.value}`);
}

async function loadSites() {
  sites.value = await request('/dashboard/sites');
}

async function loadLeads() {
  const query = new URLSearchParams();
  if (search.value.trim()) query.set('search', search.value.trim());
  if (leadSite.value) query.set('site_id', leadSite.value);
  query.set('page', String(leadPage.value));
  const response = await window.pulseFetch(`/dashboard/leads?${query}`);
  const body = await response.json().catch(() => ({}));
  if (!response.ok) throw new Error(body.message || Object.values(body.errors || {})[0]?.[0] || 'Unable to load leads.');
  leads.value = body.data;
  leadPagination.value = body.meta;
}

function searchLeads() {
  leadPage.value = 1;
  loadLeads().catch((issue) => { error.value = issue.message; });
}

function changeLeadPage(page) {
  leadPage.value = page;
  loadLeads().catch((issue) => { error.value = issue.message; });
}

function changePeriod() {
  loadAnalytics().catch((issue) => { error.value = issue.message; });
}

async function refresh() {
  error.value = '';
  loading.value = true;
  try {
    await Promise.all([loadAnalytics(), loadSites(), loadLeads()]);
  } catch (issue) {
    error.value = issue.message;
  } finally {
    loading.value = false;
  }
}

async function addSite() {
  error.value = '';
  try {
    const result = await request('/dashboard/sites', {
      method: 'POST',
      body: JSON.stringify(siteForm.value),
    });
    siteForm.value = { name: '', domain: '' };
    tokenModal.value = result.token;
    notice.value = 'Site connected. Copy the token now; it will not be shown again.';
    await Promise.all([loadSites(), loadAnalytics()]);
  } catch (issue) {
    error.value = issue.message;
  }
}

async function rotateToken(site) {
  error.value = '';
  try {
    const result = await request(`/dashboard/sites/${site.id}/token`, { method: 'POST' });
    tokenModal.value = result.token;
    notice.value = `A new token was created for ${site.name}.`;
  } catch (issue) {
    error.value = issue.message;
  }
}

async function removeSite(site) {
  if (!window.confirm(`Disconnect ${site.name}? Its events will also be removed.`)) return;
  error.value = '';
  try {
    await request(`/dashboard/sites/${site.id}`, { method: 'DELETE' });
    await Promise.all([loadSites(), loadAnalytics(), loadLeads()]);
  } catch (issue) {
    error.value = issue.message;
  }
}

async function copyToken() {
  try {
    await navigator.clipboard.writeText(tokenModal.value);
    notice.value = 'Token copied.';
  } catch {
    notice.value = 'Clipboard access was denied. Select and copy the token manually.';
  }
}

function changeTab(tab) {
  activeTab.value = tab;
  if (tab === 'Leads') loadLeads().catch((issue) => { error.value = issue.message; });
  if (tab === 'Sites') loadSites().catch((issue) => { error.value = issue.message; });
}

onMounted(refresh);
</script>

<template>
  <div class="layout">
    <aside class="sidebar">
      <a class="brand" href="/dashboard">pulse<span>sync</span><i></i></a>
      <div class="workspace-label">WORKSPACE</div>
      <div class="workspace-pill"><span class="avatar">{{ user.name?.slice(0, 1) || 'P' }}</span><span>{{ user.name || 'Workspace' }}</span><span class="chevron">⌄</span></div>
      <nav class="navigation" aria-label="Main navigation">
        <button v-for="tab in ['Overview', 'Leads', 'Sites']" :key="tab" :class="{ active: activeTab === tab }" @click="changeTab(tab)">
          <span class="nav-icon">{{ tab === 'Overview' ? '◫' : tab === 'Leads' ? '↗' : '◎' }}</span>{{ tab }}
          <span v-if="tab === 'Leads' && analytics" class="nav-count">{{ analytics.leads }}</span>
        </button>
      </nav>
      <div class="sidebar-bottom">
        <div class="help-card"><span class="help-icon">✳</span><strong>Need a hand?</strong><p>Connect your first site to get started.</p></div>
        <form method="post" action="/logout">
          <input type="hidden" name="_token" :value="csrfToken">
          <button class="logout-button" type="submit"><span class="avatar small">{{ user.name?.slice(0, 1) || 'P' }}</span>{{ user.name }}<span class="logout-label">Log out ↗</span></button>
        </form>
      </div>
    </aside>

    <main class="main-content">
      <header class="topbar">
        <div><p class="eyebrow">{{ new Date().toLocaleDateString(undefined, { weekday: 'long', month: 'long', day: 'numeric' }).toUpperCase() }}</p><h1>{{ activeTab === 'Overview' ? 'Overview' : activeTab }}</h1></div>
        <div class="top-actions">
          <label class="period-select"><span class="sr-only">Date range</span><select v-model="days" @change="changePeriod"><option :value="7">Last 7 days</option><option :value="30">Last 30 days</option><option :value="90">Last 90 days</option></select></label>
          <button class="icon-button" aria-label="Refresh dashboard" @click="refresh">↻</button>
        </div>
      </header>
      <div v-if="error" class="alert error" role="alert">{{ error }}<button @click="error = ''" aria-label="Dismiss">×</button></div>
      <div v-if="notice" class="alert success" role="status">{{ notice }}<button @click="notice = ''" aria-label="Dismiss">×</button></div>
      <div v-if="loading" class="loading-state"><span class="spinner"></span>Loading your analytics…</div>

      <template v-else-if="activeTab === 'Overview' && analytics">
        <section class="kpi-grid" aria-label="Key metrics">
          <article class="kpi-card"><div class="kpi-label">Total leads <span class="kpi-icon purple">↗</span></div><strong>{{ analytics.leads.toLocaleString() }}</strong><p>Captured in the selected period</p><div class="mini-bars"><i v-for="(bar, i) in [26,38,31,53,43,66,50,78,61,92,72,100]" :key="i" :style="{height: `${bar}%`}"></i></div></article>
          <article class="kpi-card"><div class="kpi-label">Conversion rate <span class="kpi-icon green">◉</span></div><strong>{{ analytics.conversion_rate }}<small>%</small></strong><p>Leads as a share of page views</p><div class="rate-line"><span :style="{width: `${Math.min(analytics.conversion_rate * 10, 100)}%`}"></span></div></article>
          <article class="kpi-card"><div class="kpi-label">Active sites <span class="kpi-icon orange">◎</span></div><strong>{{ analytics.active_sites }}<small>/ {{ sites.length }}</small></strong><p>Received events in the last 7 days</p><div class="site-dots"><i v-for="site in sites.slice(0, 8)" :key="site.id"></i><span v-if="!sites.length">Connect a site to start</span></div></article>
        </section>

        <section class="panel chart-panel">
          <div class="panel-heading"><div><p class="eyebrow">PERFORMANCE</p><h2>Traffic &amp; leads</h2></div><div class="chart-legend"><span><i class="legend-dot traffic"></i>Page views</span><span><i class="legend-dot lead"></i>Leads</span></div></div>
          <div v-if="!chartPoints.views" class="empty-chart">No events yet. Connect a WordPress site to see activity.</div>
          <svg v-else class="chart" viewBox="0 0 720 220" role="img" aria-label="Daily page views and leads">
            <line v-for="y in [25,75,125,175]" :key="y" x1="12" :y1="y" x2="708" :y2="y" class="grid-line"/>
            <polyline :points="chartPoints.views" class="chart-line views-line"/>
            <polyline :points="chartPoints.leads" class="chart-line leads-line"/>
          </svg>
          <div class="chart-labels"><span v-for="label in chartPoints.labels" :key="label.day">{{ new Date(label.day).toLocaleDateString(undefined, {month:'short', day:'numeric'}) }}</span></div>
        </section>

        <section class="bottom-grid">
          <div class="panel table-panel">
            <div class="panel-heading"><div><p class="eyebrow">RECENT ACTIVITY</p><h2>Latest leads</h2></div><button class="text-button" @click="changeTab('Leads')">View all →</button></div>
            <div v-if="!leads.length" class="empty-state"><span>↗</span><strong>No leads yet</strong><p>New submissions will appear here.</p></div>
            <div v-else class="lead-list"><article v-for="lead in leads.slice(0, 5)" :key="lead.id" class="lead-row"><span class="lead-avatar">{{ (lead.fields?.name || lead.fields?.email || 'L').slice(0, 1).toUpperCase() }}</span><div class="lead-info"><strong>{{ lead.fields?.name || lead.fields?.email || 'New lead' }}</strong><span>{{ lead.site }} · {{ lead.page_url }}</span></div><time>{{ new Date(lead.occurred_at).toLocaleDateString() }}</time></article></div>
          </div>
          <div class="panel urls-panel"><div class="panel-heading"><div><p class="eyebrow">WHAT'S WORKING</p><h2>Top pages</h2></div></div><div v-if="!analytics.top_urls.length" class="empty-state compact">Page views will surface your top URLs.</div><div v-else class="url-list"><div v-for="(url, index) in analytics.top_urls" :key="url.url" class="url-row"><span class="url-rank">0{{ index + 1 }}</span><span class="url-name">{{ url.url }}</span><strong>{{ Number(url.page_views).toLocaleString() }}</strong></div></div></div>
        </section>
      </template>

      <section v-else-if="activeTab === 'Leads'" class="panel leads-page">
        <div class="panel-heading"><div><p class="eyebrow">YOUR AUDIENCE</p><h2>Lead explorer</h2></div><span class="result-count">{{ leads.length }} results</span></div>
        <label class="search-box"><span>⌕</span><input v-model="search" placeholder="Search leads by name, email or page…" @input="searchLeads"></label>
        <label class="lead-filter"><span>Filter by site</span><select v-model="leadSite" @change="searchLeads"><option value="">All sites</option><option v-for="site in sites" :key="site.id" :value="site.id">{{ site.name }}</option></select></label>
        <div v-if="!leads.length" class="empty-state"><span>↗</span><strong>No matching leads</strong><p>Try a different search or connect a site.</p></div>
        <div v-else class="lead-table-wrap"><table class="lead-table"><thead><tr><th>CONTACT</th><th>SITE</th><th>PAGE</th><th>CAPTURED</th><th></th></tr></thead><tbody><tr v-for="lead in leads" :key="lead.id"><td><strong>{{ lead.fields?.name || lead.fields?.email || 'New lead' }}</strong><span>{{ lead.fields?.email || 'No email provided' }}</span></td><td>{{ lead.site }}</td><td class="truncate">{{ lead.page_url }}</td><td>{{ new Date(lead.occurred_at).toLocaleDateString() }}</td><td><button class="text-button" @click="selectedLead = lead">Inspect</button></td></tr></tbody></table></div>
        <div v-if="leadPagination.last_page > 1" class="pagination"><button :disabled="leadPagination.current_page <= 1" @click="changeLeadPage(leadPagination.current_page - 1)">← Previous</button><span>Page {{ leadPagination.current_page }} of {{ leadPagination.last_page }} · {{ leadPagination.total }} leads</span><button :disabled="leadPagination.current_page >= leadPagination.last_page" @click="changeLeadPage(leadPagination.current_page + 1)">Next →</button></div>
      </section>

      <section v-else-if="activeTab === 'Sites'" class="sites-page">
        <div class="panel site-form-panel"><div class="panel-heading"><div><p class="eyebrow">GET CONNECTED</p><h2>Add a WordPress site</h2></div><span class="add-icon">＋</span></div><form class="site-form" @submit.prevent="addSite"><label>Site name<input v-model.trim="siteForm.name" placeholder="My website" required maxlength="120"></label><label>Website URL<input v-model.trim="siteForm.domain" type="url" placeholder="https://example.com" required></label><button class="primary-button" type="submit">Add site <span aria-hidden="true">→</span></button></form></div>
        <div class="sites-list"><div class="section-title"><h2>Connected sites</h2><span>{{ sites.length }} total</span></div><div v-if="!sites.length" class="panel empty-sites"><span>◎</span><strong>Your sites will appear here</strong><p>Add a site above to generate its connector token.</p></div><article v-for="site in sites" :key="site.id" class="panel site-row"><span class="site-mark">◎</span><div class="site-info"><strong>{{ site.name }}</strong><a :href="`https://${site.domain}`" target="_blank" rel="noreferrer">{{ site.domain }} ↗</a></div><span class="site-leads">{{ site.lead_count }} leads</span><button class="secondary-button small-button" @click="rotateToken(site)">Rotate key</button><button class="remove-button" :aria-label="`Disconnect ${site.name}`" @click="removeSite(site)">×</button></article></div>
      </section>
    </main>

    <div v-if="tokenModal" class="modal-backdrop" @click.self="tokenModal = ''">
      <section class="token-modal" role="dialog" aria-modal="true" aria-labelledby="token-title">
        <button class="modal-close" aria-label="Close" @click="tokenModal = ''">×</button><span class="modal-check">✓</span><p class="eyebrow">SITE TOKEN</p><h2 id="token-title">Connect your WordPress plugin</h2><p>Copy this token into Settings → PulseSync. For security, it won’t be displayed again.</p><div class="token-value">{{ tokenModal }}</div><button class="primary-button full-button" @click="copyToken">Copy API token</button>
      </section>
    </div>
    <div v-if="selectedLead" class="modal-backdrop" @click.self="selectedLead = null">
      <section class="token-modal lead-modal" role="dialog" aria-modal="true" aria-labelledby="lead-title">
        <button class="modal-close" aria-label="Close" @click="selectedLead = null">×</button><p class="eyebrow">LEAD DETAILS</p><h2 id="lead-title">{{ selectedLead.fields?.name || selectedLead.fields?.email || 'New lead' }}</h2><p>{{ selectedLead.site }} · {{ new Date(selectedLead.occurred_at).toLocaleString() }}</p>
        <dl class="metadata-list"><template v-for="(value, key) in selectedLead.fields" :key="key"><dt>{{ key }}</dt><dd>{{ value }}</dd></template><template v-for="(value, key) in selectedLead.metadata" :key="`metadata-${key}`"><dt>metadata.{{ key }}</dt><dd>{{ typeof value === 'object' ? JSON.stringify(value) : value }}</dd></template></dl>
        <a class="source-link" :href="selectedLead.page_url" target="_blank" rel="noreferrer">Open captured page ↗</a>
      </section>
    </div>
  </div>
</template>
