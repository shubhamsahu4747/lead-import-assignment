import React, { useState, useEffect } from 'react';
import { X, Download, AlertTriangle, Loader2 } from 'lucide-react';
import { importApi } from '../services/importApi';

export default function FailuresModal({ importId, onClose }) {
  const [failures, setFailures] = useState([]);
  const [loading, setLoading] = useState(true);
  const [page, setPage] = useState(1);
  const [pagination, setPagination] = useState(null);
  const [error, setError] = useState(null);

  useEffect(() => {
    if (!importId) return;

    let isMounted = true;
    setLoading(true);

    importApi.getImportFailures(importId, page, 15)
      .then((res) => {
        if (isMounted) {
          setFailures(res.data || []);
          setPagination({
            current_page: res.current_page,
            last_page: res.last_page,
            total: res.total,
          });
          setError(null);
        }
      })
      .catch((err) => {
        if (isMounted) {
          setError(err.message || 'Failed to load failure records.');
        }
      })
      .finally(() => {
        if (isMounted) setLoading(false);
      });

    return () => {
      isMounted = false;
    };
  }, [importId, page]);

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/50 backdrop-blur-sm animate-fade-in">
      <div className="bg-white rounded-2xl shadow-2xl border border-slate-200 max-w-4xl w-full max-h-[90vh] flex flex-col overflow-hidden">
        {/* Header */}
        <div className="p-6 border-b border-slate-100 flex items-center justify-between">
          <div className="flex items-center gap-3">
            <div className="p-2 bg-red-100 text-red-600 rounded-lg">
              <AlertTriangle className="w-5 h-5" />
            </div>
            <div>
              <h3 className="text-lg font-bold text-slate-900">
                Failed Records Inspector — Import #{importId}
              </h3>
              <p className="text-xs text-slate-500 mt-0.5">
                Exact row numbers and failure reasons for invalid or duplicate records
              </p>
            </div>
          </div>

          <div className="flex items-center gap-3">
            <a
              href={importApi.getFailedCsvDownloadUrl(importId)}
              download={`failed_records_${importId}.csv`}
              className="px-3.5 py-1.5 text-xs font-semibold text-red-700 bg-red-50 hover:bg-red-100 border border-red-200 rounded-lg flex items-center gap-1.5 transition-colors"
            >
              <Download className="w-3.5 h-3.5" />
              Download CSV
            </a>
            <button
              onClick={onClose}
              className="p-1.5 text-slate-400 hover:text-slate-700 hover:bg-slate-100 rounded-lg transition-colors"
            >
              <X className="w-5 h-5" />
            </button>
          </div>
        </div>

        {/* Content */}
        <div className="p-6 overflow-y-auto flex-1">
          {error && (
            <div className="p-4 bg-red-50 text-red-800 text-sm rounded-lg mb-4">
              {error}
            </div>
          )}

          {loading ? (
            <div className="py-16 text-center text-slate-400">
              <Loader2 className="w-6 h-6 animate-spin mx-auto text-blue-600 mb-2" />
              Loading failed records...
            </div>
          ) : failures.length === 0 ? (
            <div className="py-16 text-center text-slate-400">
              No failed records found for this import.
            </div>
          ) : (
            <div className="overflow-x-auto border border-slate-200 rounded-lg">
              <table className="w-full text-left text-xs text-slate-600">
                <thead className="bg-slate-50 text-slate-500 font-semibold uppercase tracking-wider border-b border-slate-200">
                  <tr>
                    <th className="py-3 px-4">Row #</th>
                    <th className="py-3 px-4">Name</th>
                    <th className="py-3 px-4">Email</th>
                    <th className="py-3 px-4">Phone</th>
                    <th className="py-3 px-4">Company</th>
                    <th className="py-3 px-4">Failure Reason</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-slate-100">
                  {failures.map((item) => (
                    <tr key={item.id} className="hover:bg-slate-50/60">
                      <td className="py-3 px-4 font-mono font-medium text-slate-900">
                        {item.row_number}
                      </td>
                      <td className="py-3 px-4 text-slate-700">{item.name || <span className="text-slate-400 italic">empty</span>}</td>
                      <td className="py-3 px-4 font-mono text-slate-700">{item.email || <span className="text-slate-400 italic">empty</span>}</td>
                      <td className="py-3 px-4 font-mono text-slate-700">{item.phone || <span className="text-slate-400 italic">empty</span>}</td>
                      <td className="py-3 px-4 text-slate-700">{item.company || <span className="text-slate-400 italic">empty</span>}</td>
                      <td className="py-3 px-4">
                        <span className="inline-block px-2 py-0.5 rounded text-[11px] font-semibold bg-red-100 text-red-800">
                          {item.reason}
                        </span>
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          )}
        </div>

        {/* Footer Pagination */}
        {pagination && pagination.last_page > 1 && (
          <div className="p-4 border-t border-slate-100 bg-slate-50 flex items-center justify-between text-xs text-slate-500">
            <div>
              Page {pagination.current_page} of {pagination.last_page} ({pagination.total} total failures)
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
                onClick={() => setPage((p) => Math.min(pagination.last_page, p + 1))}
                disabled={page >= pagination.last_page}
                className="px-3 py-1.5 rounded border border-slate-200 bg-white text-slate-600 disabled:opacity-40 disabled:cursor-not-allowed hover:bg-slate-50"
              >
                Next
              </button>
            </div>
          </div>
        )}
      </div>
    </div>
  );
}
