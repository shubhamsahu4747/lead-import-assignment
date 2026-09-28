import React, { useState, useEffect } from 'react';
import { Search, Users, Loader2, RefreshCw } from 'lucide-react';
import { importApi } from '../services/importApi';

export default function LeadsExplorer() {
  const [leads, setLeads] = useState([]);
  const [loading, setLoading] = useState(true);
  const [search, setSearch] = useState('');
  const [page, setPage] = useState(1);
  const [meta, setMeta] = useState(null);

  const fetchLeads = (currentPage = 1, searchQuery = search) => {
    setLoading(true);
    importApi.getLeads(currentPage, searchQuery, 15)
      .then((res) => {
        setLeads(res.data || []);
        setMeta(res.meta || null);
      })
      .catch((err) => {
        console.error('Failed to fetch leads:', err);
      })
      .finally(() => {
        setLoading(false);
      });
  };

  useEffect(() => {
    fetchLeads(page, search);
  }, [page]);

  const handleSearchSubmit = (e) => {
    e.preventDefault();
    setPage(1);
    fetchLeads(1, search);
  };

  const formatNumber = (num) => new Intl.NumberFormat().format(num || 0);

  return (
    <div className="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
      <div className="p-6 border-b border-slate-100 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
          <div className="flex items-center gap-2">
            <h2 className="text-lg font-bold text-slate-900">Database Leads Explorer</h2>
            {meta && (
              <span className="px-2.5 py-0.5 rounded-full text-xs font-semibold bg-blue-100 text-blue-700">
                {formatNumber(meta.total)} Total Leads
              </span>
            )}
          </div>
          <p className="text-xs text-slate-500 mt-0.5">
            Live query inspection of leads stored in MySQL database
          </p>
        </div>

        {/* Search Bar */}
        <div className="flex items-center gap-2">
          <form onSubmit={handleSearchSubmit} className="relative">
            <Search className="w-4 h-4 text-slate-400 absolute left-3 top-2.5 pointer-events-none" />
            <input
              type="text"
              placeholder="Search by email, name, company..."
              value={search}
              onChange={(e) => setSearch(e.target.value)}
              className="pl-9 pr-4 py-2 text-xs bg-slate-50 border border-slate-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 w-64 transition-all"
            />
          </form>

          <button
            onClick={() => fetchLeads(page, search)}
            className="p-2 text-slate-500 hover:text-slate-800 hover:bg-slate-100 rounded-lg transition-colors"
            title="Refresh Leads"
          >
            <RefreshCw className={`w-4 h-4 ${loading ? 'animate-spin' : ''}`} />
          </button>
        </div>
      </div>

      <div className="overflow-x-auto">
        <table className="w-full text-left text-xs text-slate-600">
          <thead className="bg-slate-50 text-slate-500 font-semibold uppercase tracking-wider border-b border-slate-200">
            <tr>
              <th className="py-3 px-6">ID</th>
              <th className="py-3 px-4">Lead Name</th>
              <th className="py-3 px-4">Email</th>
              <th className="py-3 px-4">Phone</th>
              <th className="py-3 px-4">Company</th>
              <th className="py-3 px-6">Created At</th>
            </tr>
          </thead>
          <tbody className="divide-y divide-slate-100">
            {loading ? (
              <tr>
                <td colSpan={6} className="py-12 text-center text-slate-400">
                  <Loader2 className="w-6 h-6 animate-spin mx-auto text-blue-600 mb-2" />
                  Loading leads...
                </td>
              </tr>
            ) : leads.length === 0 ? (
              <tr>
                <td colSpan={6} className="py-12 text-center text-slate-400">
                  No leads found. Run an import to see records here.
                </td>
              </tr>
            ) : (
              leads.map((lead) => (
                <tr key={lead.id} className="hover:bg-slate-50/60 transition-colors">
                  <td className="py-3.5 px-6 font-mono text-slate-400">#{lead.id}</td>
                  <td className="py-3.5 px-4 font-semibold text-slate-900">{lead.name}</td>
                  <td className="py-3.5 px-4 font-mono text-blue-600">{lead.email}</td>
                  <td className="py-3.5 px-4 font-mono text-slate-600">{lead.phone}</td>
                  <td className="py-3.5 px-4 text-slate-700">{lead.company}</td>
                  <td className="py-3.5 px-6 text-slate-400">
                    {new Date(lead.created_at).toLocaleString([], {
                      month: 'short',
                      day: 'numeric',
                      hour: '2-digit',
                      minute: '2-digit',
                    })}
                  </td>
                </tr>
              ))
            )}
          </tbody>
        </table>
      </div>

      {/* Pagination */}
      {meta && meta.last_page > 1 && (
        <div className="p-4 border-t border-slate-100 bg-slate-50 flex items-center justify-between text-xs text-slate-500">
          <div>
            Showing page {meta.current_page} of {meta.last_page} ({meta.total} total leads)
          </div>
          <div className="flex items-center gap-2">
            <button
              onClick={() => setPage((p) => Math.max(1, p - 1))}
              disabled={page <= 1}
              className="px-3 py-1.5 rounded border border-slate-200 bg-white text-slate-600 disabled:opacity-40 disabled:cursor-not-allowed hover:bg-slate-50"
            >
              Previous
            </button>
            <button
              onClick={() => setPage((p) => Math.min(meta.last_page, p + 1))}
              disabled={page >= meta.last_page}
              className="px-3 py-1.5 rounded border border-slate-200 bg-white text-slate-600 disabled:opacity-40 disabled:cursor-not-allowed hover:bg-slate-50"
            >
              Next
            </button>
          </div>
        </div>
      )}
    </div>
  );
}
