import {
    Livewire,
    Alpine,
} from '../../vendor/livewire/livewire/dist/livewire.esm';

window.Alpine = Alpine;

/**
 * Dashboard grid — client-side filtering/search/tabs, mirroring the approved
 * mockup. The `apps` array is rendered by the server from the database; this
 * component only filters and counts (no access logic is decided here — each
 * app already arrives with a server-computed `can_access` flag).
 */
Alpine.data('dashboard', (apps) => ({
    apps,
    tab: 'all',
    status: 'all',
    access: 'all',
    cat: 'all',
    q: '',
    cats: ['governance', 'economy', 'kinerja', 'gawai', 'rencana', 'uang', 'pajak', 'kesehatan', 'data', 'wisata', 'umum'],
    toastMsg: '',
    toastShow: false,
    _toastTimer: null,

    fmt(n) {
        return Number(n).toLocaleString('id-ID');
    },

    label(key) {
        return key.charAt(0).toUpperCase() + key.slice(1);
    },

    initials(name) {
        return name.split(/\s+/).map((w) => w[0]).join('').slice(0, 2).toUpperCase();
    },

    matches(a) {
        const q = this.q.toLowerCase();
        return (
            (this.tab === 'all' || (this.tab === 'baru' ? a.is_new : a.group === this.tab)) &&
            (this.status === 'all' || (this.status === 'on' ? a.active : !a.active)) &&
            (this.access === 'all' || (this.access === 'yes') === a.can_access) &&
            (this.cat === 'all' || a.category === this.cat) &&
            (a.name + ' ' + a.opd + ' ' + (a.description || '')).toLowerCase().includes(q)
        );
    },

    get filtered() {
        return this.apps.filter((a) => this.matches(a));
    },

    get topApps() {
        return [...this.apps]
            .filter((a) => a.active)
            .sort((x, y) =>
                (y.day_visits - x.day_visits) ||
                (y.month_visits - x.month_visits)
            )
            .slice(0, 5);
    },

    countGroup(g) {
        return this.apps.filter((a) => (g === 'all' ? true : g === 'baru' ? a.is_new : a.group === g)).length;
    },
    countStatus(s) {
        return this.apps.filter((a) => (s === 'all' ? true : s === 'on' ? a.active : !a.active)).length;
    },
    countAccess(v) {
        return this.apps.filter((a) => (v === 'all' ? true : (v === 'yes') === a.can_access)).length;
    },
    countCat(c) {
        return this.apps.filter((a) => a.category === c).length;
    },

    denied() {
        this.toast('Anda tidak memiliki akses ke aplikasi ini.');
    },
    toast(msg) {
        this.toastMsg = msg;
        this.toastShow = true;
        clearTimeout(this._toastTimer);
        this._toastTimer = setTimeout(() => (this.toastShow = false), 2600);
    },
}));

Livewire.start();
