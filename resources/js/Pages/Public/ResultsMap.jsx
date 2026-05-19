import { useDeferredValue, useMemo, useState } from 'react';
import AppLayout from '@/Layouts/AppLayout';
import { Link, router } from '@inertiajs/react';
import LeafletMap from '@/Components/Map/LeafletMap';
import useInertiaPrefetch from '@/Hooks/useInertiaPrefetch';
import { publicElectionTitle } from '@/Utils/publicElection';

// ── Filter options ────────────────────────────────────────────────────────────
const STATUS_FILTERS = [
    { key: 'all',          label: 'All',          color: '#64748b' },
    { key: 'not_reported', label: 'Not Reported',  color: '#94a3b8' },
    { key: 'submitted',    label: 'Submitted',     color: '#ef4444' },
    { key: 'in_progress',  label: 'Under Review',  color: '#f59e0b' },
    { key: 'certified',    label: 'Certified',     color: '#22c55e' },
];

function stationCategory(station) {
    const s = station.status;
    if (s === 'nationally_certified') return 'certified';
    if (['ward_certified', 'pending_constituency', 'constituency_certified',
         'pending_admin_area', 'admin_area_certified', 'pending_national'].includes(s)) return 'in_progress';
    if (['submitted', 'pending_ward', 'pending_party_acceptance'].includes(s)) return 'submitted';
    return 'not_reported';
}

function compareText(a, b) {
    return String(a || '').localeCompare(String(b || ''), undefined, { numeric: true, sensitivity: 'base' });
}

function buildOptions(stations, field) {
    const counts = new Map();
    stations.forEach(s => {
        const v = s[field];
        if (v) counts.set(v, (counts.get(v) || 0) + 1);
    });
    return Array.from(counts, ([value, count]) => ({ value, count }))
        .sort((a, b) => compareText(a.value, b.value));
}

export default function ResultsMap({ election, elections = [], selectedElectionId, stations = [] }) {
    const param = selectedElectionId ? `?election=${selectedElectionId}` : '';

    const [searchTerm,           setSearchTerm]           = useState('');
    const [selectedRegion,       setSelectedRegion]       = useState('all');
    const [selectedConstituency, setSelectedConstituency] = useState('all');
    const [statusFilter,         setStatusFilter]         = useState('all');
    const [sidebarOpen,          setSidebarOpen]          = useState(false);

    const deferredSearch = useDeferredValue(searchTerm);
    useInertiaPrefetch([`/results${param}`, `/results/stations${param}`]);

    const stationList = stations || [];

    // Summary stats
    const summary = useMemo(() => {
        const certified   = stationList.filter(s => s.status === 'nationally_certified').length;
        const inProgress  = stationList.filter(s => stationCategory(s) === 'in_progress').length;
        const submitted   = stationList.filter(s => stationCategory(s) === 'submitted').length;
        const notReported = stationList.filter(s => stationCategory(s) === 'not_reported').length;
        const votesCast   = stationList.reduce((sum, s) => sum + Number(s.total_votes_cast || 0), 0);
        return { total: stationList.length, certified, inProgress, submitted, notReported, votesCast };
    }, [stationList]);

    const regionOptions = useMemo(() => buildOptions(stationList, 'admin_area_name'), [stationList]);

    const regionScoped = useMemo(() => (
        selectedRegion === 'all' ? stationList : stationList.filter(s => s.admin_area_name === selectedRegion)
    ), [stationList, selectedRegion]);

    const constituencyOptions = useMemo(() => buildOptions(regionScoped, 'constituency_name'), [regionScoped]);

    const locationFiltered = useMemo(() => (
        regionScoped.filter(s => selectedConstituency === 'all' || s.constituency_name === selectedConstituency)
    ), [regionScoped, selectedConstituency]);

    const filteredStations = useMemo(() => {
        const q = deferredSearch.trim().toLowerCase();
        return locationFiltered.filter(s => {
            const catMatch = statusFilter === 'all' || stationCategory(s) === statusFilter;
            const haystack = [s.name, s.code, s.admin_area_name, s.constituency_name, s.ward_name].filter(Boolean).join(' ').toLowerCase();
            return catMatch && (!q || haystack.includes(q));
        });
    }, [locationFiltered, deferredSearch, statusFilter]);

    // Status filter counts (based on location filter)
    const filterCounts = useMemo(() => ({
        all:          locationFiltered.length,
        not_reported: locationFiltered.filter(s => stationCategory(s) === 'not_reported').length,
        submitted:    locationFiltered.filter(s => stationCategory(s) === 'submitted').length,
        in_progress:  locationFiltered.filter(s => stationCategory(s) === 'in_progress').length,
        certified:    locationFiltered.filter(s => stationCategory(s) === 'certified').length,
    }), [locationFiltered]);

    if (!election) {
        return (
            <AppLayout>
                <div className="flex flex-col items-center justify-center min-h-[70vh] bg-slate-50 p-8">
                    <div className="text-6xl mb-4">🗺️</div>
                    <h1 className="text-2xl font-bold text-slate-900 mb-2">No election available</h1>
                    <p className="text-slate-600 mb-6">No active election is configured for public display.</p>
                    <Link href="/" className="bg-iec-pink-600 text-white font-bold px-6 py-3 rounded-xl hover:bg-iec-pink-700">
                        Back to Home
                    </Link>
                </div>
            </AppLayout>
        );
    }

    return (
        <AppLayout>
            {/* ── Full-screen map layout ─────────────────────────────────────── */}
            <div className="flex flex-col bg-slate-950" style={{ minHeight: 'calc(100vh - 64px)' }}>

                {/* ── Compact top bar ──────────────────────────────────────────── */}
                <div className="bg-slate-900 border-b border-slate-800 px-4 py-3 flex-shrink-0">
                    <div className="flex flex-wrap items-center gap-3 justify-between">
                        {/* Left: election info */}
                        <div className="flex items-center gap-3 min-w-0">
                            <div className="hidden sm:block">
                                <p className="text-[10px] font-bold uppercase tracking-widest text-slate-500">
                                    IEC Live Map
                                </p>
                                <p className="text-sm font-bold text-white truncate max-w-xs">
                                    {publicElectionTitle(election)}
                                </p>
                            </div>

                            {/* Election selector */}
                            {elections.length > 1 && (
                                <select
                                    value={selectedElectionId || ''}
                                    onChange={e => router.get('/results/map', { election: e.target.value }, { preserveScroll: false })}
                                    className="text-xs bg-slate-800 border border-slate-700 text-white rounded-lg px-2 py-1.5 focus:outline-none focus:border-slate-600"
                                >
                                    {elections.map(el => (
                                        <option key={el.id} value={el.id}>{publicElectionTitle(el)}</option>
                                    ))}
                                </select>
                            )}
                        </div>

                        {/* Right: quick stat chips */}
                        <div className="flex items-center gap-2 flex-wrap">
                            <StatChip color="#22c55e" label="Certified"   count={summary.certified}   />
                            <StatChip color="#f59e0b" label="In Review"   count={summary.inProgress}  />
                            <StatChip color="#ef4444" label="Submitted"   count={summary.submitted}   />
                            <StatChip color="#94a3b8" label="Unreported"  count={summary.notReported} />
                            <span className="text-xs text-slate-500 hidden md:block">
                                {filteredStations.length.toLocaleString()} / {summary.total.toLocaleString()} shown
                            </span>

                            {/* Mobile sidebar toggle */}
                            <button
                                onClick={() => setSidebarOpen(v => !v)}
                                className="lg:hidden ml-2 bg-slate-800 border border-slate-700 text-white rounded-lg px-3 py-1.5 text-xs font-semibold"
                            >
                                ⚙ Filters
                            </button>

                            {/* Nav links */}
                            <Link href={`/results${param}`}
                                className="hidden sm:flex items-center gap-1.5 bg-slate-800 hover:bg-slate-700 border border-slate-700 text-slate-300 text-xs font-semibold px-3 py-1.5 rounded-lg transition-colors">
                                ← Results
                            </Link>
                        </div>
                    </div>
                </div>

                {/* ── Main content: sidebar + map ───────────────────────────────── */}
                <div className="flex flex-1 min-h-0" style={{ height: 'calc(100vh - 130px)' }}>

                    {/* ── Left sidebar — filters (desktop always visible, mobile overlay) */}
                    <div className={`
                        ${sidebarOpen ? 'flex' : 'hidden'} lg:flex
                        flex-col w-72 bg-slate-900 border-r border-slate-800
                        overflow-y-auto flex-shrink-0 z-30
                        ${sidebarOpen ? 'absolute left-0 top-0 bottom-0 shadow-2xl' : 'relative'}
                    `}>
                        {/* Sidebar header */}
                        <div className="flex items-center justify-between px-4 py-3 border-b border-slate-800">
                            <span className="text-xs font-bold uppercase tracking-widest text-slate-400">Filters</span>
                            <button onClick={() => setSidebarOpen(false)} className="lg:hidden text-slate-500 hover:text-white text-lg">✕</button>
                        </div>

                        <div className="p-4 space-y-5 flex-1">
                            {/* Search */}
                            <div>
                                <label className="block text-xs font-bold uppercase tracking-wide text-slate-500 mb-2">
                                    Search Station
                                </label>
                                <input
                                    type="search"
                                    value={searchTerm}
                                    onChange={e => setSearchTerm(e.target.value)}
                                    placeholder="Name, code, ward…"
                                    className="w-full bg-slate-800 border border-slate-700 text-white text-sm rounded-lg px-3 py-2 placeholder-slate-500 focus:outline-none focus:border-slate-500"
                                />
                            </div>

                            {/* Region */}
                            <div>
                                <label className="block text-xs font-bold uppercase tracking-wide text-slate-500 mb-2">
                                    Region
                                </label>
                                <select
                                    value={selectedRegion}
                                    onChange={e => { setSelectedRegion(e.target.value); setSelectedConstituency('all'); }}
                                    className="w-full bg-slate-800 border border-slate-700 text-white text-sm rounded-lg px-3 py-2 focus:outline-none focus:border-slate-500"
                                >
                                    <option value="all">All Regions ({summary.total})</option>
                                    {regionOptions.map(o => (
                                        <option key={o.value} value={o.value}>{o.value} ({o.count})</option>
                                    ))}
                                </select>
                            </div>

                            {/* Constituency */}
                            <div>
                                <label className="block text-xs font-bold uppercase tracking-wide text-slate-500 mb-2">
                                    Constituency
                                </label>
                                <select
                                    value={selectedConstituency}
                                    onChange={e => setSelectedConstituency(e.target.value)}
                                    className="w-full bg-slate-800 border border-slate-700 text-white text-sm rounded-lg px-3 py-2 focus:outline-none focus:border-slate-500"
                                >
                                    <option value="all">All ({regionScoped.length})</option>
                                    {constituencyOptions.map(o => (
                                        <option key={o.value} value={o.value}>{o.value} ({o.count})</option>
                                    ))}
                                </select>
                            </div>

                            {/* Status filter */}
                            <div>
                                <label className="block text-xs font-bold uppercase tracking-wide text-slate-500 mb-2">
                                    Result Status
                                </label>
                                <div className="space-y-1.5">
                                    {STATUS_FILTERS.map(f => (
                                        <button
                                            key={f.key}
                                            onClick={() => setStatusFilter(f.key)}
                                            className={`w-full flex items-center justify-between px-3 py-2 rounded-lg text-sm font-medium transition-all border ${
                                                statusFilter === f.key
                                                    ? 'border-transparent text-white'
                                                    : 'border-slate-700 text-slate-400 hover:border-slate-600 hover:text-slate-200'
                                            }`}
                                            style={statusFilter === f.key ? { backgroundColor: f.color + '33', borderColor: f.color + '88' } : {}}
                                        >
                                            <div className="flex items-center gap-2.5">
                                                <span className="w-3 h-3 rounded-full flex-shrink-0"
                                                    style={{ backgroundColor: f.key === 'all' ? '#64748b' : f.color }} />
                                                {f.label}
                                            </div>
                                            <span className="text-xs tabular-nums font-bold"
                                                style={{ color: statusFilter === f.key ? f.color : '#64748b' }}>
                                                {filterCounts[f.key] ?? 0}
                                            </span>
                                        </button>
                                    ))}
                                </div>
                            </div>

                            {/* Reset */}
                            {(searchTerm || selectedRegion !== 'all' || selectedConstituency !== 'all' || statusFilter !== 'all') && (
                                <button
                                    onClick={() => {
                                        setSearchTerm('');
                                        setSelectedRegion('all');
                                        setSelectedConstituency('all');
                                        setStatusFilter('all');
                                    }}
                                    className="w-full py-2 border border-slate-700 text-slate-400 hover:text-white hover:border-slate-500 rounded-lg text-sm font-semibold transition-colors"
                                >
                                    ✕ Clear Filters
                                </button>
                            )}
                        </div>

                        {/* Sidebar footer */}
                        <div className="p-4 border-t border-slate-800">
                            <p className="text-[10px] text-slate-600 leading-relaxed">
                                🔴 Submitted, not yet approved · 🟡 Approval in progress · 🟢 Nationally certified
                            </p>
                        </div>
                    </div>

                    {/* ── Map area — fills remaining space ─────────────────────────── */}
                    <div className="flex-1 relative min-w-0">
                        {/* Showing count — floating top center */}
                        {searchTerm !== deferredSearch && (
                            <div className="absolute top-4 left-1/2 -translate-x-1/2 z-[1000] bg-slate-900/90 text-white text-xs px-3 py-1.5 rounded-full border border-slate-700">
                                Filtering…
                            </div>
                        )}

                        <LeafletMap
                            stations={filteredStations}
                            height="100%"
                        />
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}

function StatChip({ color, label, count }) {
    return (
        <div className="flex items-center gap-1.5 bg-slate-800 rounded-lg px-2.5 py-1.5 border border-slate-700">
            <span className="w-2.5 h-2.5 rounded-full flex-shrink-0" style={{ backgroundColor: color }} />
            <span className="text-xs text-slate-400">{label}</span>
            <span className="text-xs font-bold tabular-nums" style={{ color }}>
                {count.toLocaleString()}
            </span>
        </div>
    );
}
