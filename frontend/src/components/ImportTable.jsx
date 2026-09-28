import React from 'react';
import { Download, Eye, CheckCircle2, Clock, XCircle, Loader2 } from 'lucide-react';
import { importApi } from '../services/importApi';

export default function ImportTable({
  imports = [],
  loading = false,
  onSelectImport,
  onViewFailures,
  pagination = null,
  onPageChange,
}) {
  const formatNumber = (num) => new Intl.NumberFormat().format(num || 0);

  const formatDate = (dateString) => {
    if (!dateString) return '—';
    const d = new Date(dateString);
    return d.toLocaleString([], {
      month: 'short',
      day: 'numeric',
      hour: '2-digit',
      minute: '2-digit',
    });
  };

  const getStatusBadge = (status) => {
    switch (status) {
      case 'processing':
        return (
          <span className="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-semibold bg-blue-100 text-blue-800">
            <Loader2 className="w-3 h-3 animate-spin" />
            Processing
          </span>
        );
      case 'completed':
        return (
          <span className="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-semibold bg-green-100 text-green-800">
            <CheckCircle2 className="w-3 h-3" />
            Completed
          </span>
        );
      case 'failed':
        return (
          <span className="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-semibold bg-red-100 text-red-800">
            <XCircle className="w-3 h-3" />
            Failed
          </span>
        );
      default:
        return (
          <span className="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-semibold bg-amber-100 text-amber-800">
            <Clock className="w-3 h-3" />
            Pending
          </span>
        );
    }
  };

  return (
    <div className="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
      <div className="p-6 border-b border-slate-100 flex items-center justify-between">
        <div>
          <h2 className="text-lg font-bold text-slate-900">Import History</h2>
          <p className="text-xs text-slate-500 mt-0.5">Historical log of past batch import runs</p>
        </div>
      </div>

      <div className="overflow-x-auto">
        <table className="w-full text-left text-sm text-slate-600">
          <thead className="bg-slate-50 text-xs font-semibold uppercase tracking-wider text-slate-500 border-b border-slate-200">
            <tr>
              <th className="py-3.5 px-6">ID & File</th>
              <th className="py-3.5 px-4">Status</th>
              <th className="py-3.5 px-4 text-right">Total</th>
              <th className="py-3.5 px-4 text-right">Success</th>
              <th className="py-3.5 px-4 text-right">Failed</th>
              <th className="py-3.5 px-4">Created</th>
              <th className="py-3.5 px-4">Completed</th>
              <th className="py-3.5 px-6 text-right">Actions</th>
            </tr>
          </thead>
          <tbody className="divide-y divide-slate-100">
            {loading ? (
              <tr>
                <td colSpan={8} className="py-12 text-center text-slate-400">
                  <Loader2 className="w-6 h-6 animate-spin mx-auto text-blue-600 mb-2" />
                  Loading imports...
                </td>
              </tr>
            ) : imports.length === 0 ? (
              <tr>
                <td colSpan={8} className="py-12 text-center text-slate-400">
                  No imports recorded yet. Upload a CSV to get started.
                </td>
              </tr>
            ) : (
              imports.map((item) => (
                <tr key={item.id} className="hover:bg-slate-50/80 transition-colors">
                  <td className="py-4 px-6 font-medium text-slate-900">
                    <div className="flex items-center gap-2">
                      <span className="text-xs text-slate-400 font-mono">#{item.id}</span>
                      <span className="truncate max-w-[200px]" title={item.original_filename}>
                        {item.original_filename}
                      </span>
                    </div>
                  </td>
                  <td className="py-4 px-4">{getStatusBadge(item.status)}</td>
                  <td className="py-4 px-4 text-right font-mono font-medium">{formatNumber(item.total_records)}</td>
                  <td className="py-4 px-4 text-right font-mono font-medium text-green-600">{formatNumber(item.success_count)}</td>
                  <td className="py-4 px-4 text-right font-mono font-medium text-red-600">
                    {formatNumber(item.failed_count)}
                  </td>
                  <td className="py-4 px-4 text-xs text-slate-500 whitespace-nowrap">{formatDate(item.created_at)}</td>
                  <td className="py-4 px-4 text-xs text-slate-500 whitespace-nowrap">{formatDate(item.completed_at)}</td>
                  <td className="py-4 px-6 text-right">
                    <div className="flex items-center justify-end gap-2">
                      {onSelectImport && (
                        <button
                          onClick={() => onSelectImport(item.id)}
                          className="p-1.5 text-slate-500 hover:text-blue-600 hover:bg-blue-50 rounded transition-colors"
                          title="View live progress / details"
                        >
                          <Eye className="w-4 h-4" />
                        </button>
                      )}

                      {item.failed_count > 0 && (
                        <>
                          {onViewFailures && (
                            <button
                              onClick={() => onViewFailures(item.id)}
                              className="px-2 py-1 text-xs font-semibold text-slate-700 bg-slate-100 hover:bg-slate-200 rounded transition-colors"
                            >
                              Failures
                            </button>
                          )}
                          <a
                            href={importApi.getFailedCsvDownloadUrl(item.id)}
                            download={`failed_records_${item.id}.csv`}
                            className="p-1.5 text-red-600 hover:text-red-800 hover:bg-red-50 rounded transition-colors"
                            title="Download failed_records.csv"
                          >
                            <Download className="w-4 h-4" />
                          </a>
                        </>
                      )}
                    </div>
                  </td>
                </tr>
              ))
            )}
          </tbody>
        </table>
      </div>

      {/* Pagination Controls */}
      {pagination && pagination.last_page > 1 && (
        <div className="p-4 border-t border-slate-100 flex items-center justify-between text-xs text-slate-500">
          <div>
            Showing page {pagination.current_page} of {pagination.last_page} ({pagination.total} total imports)
          </div>
          <div className="flex items-center gap-2">
            <button
              onClick={() => onPageChange(pagination.current_page - 1)}
              disabled={pagination.current_page <= 1}
              className="px-3 py-1.5 rounded border border-slate-200 text-slate-600 disabled:opacity-40 disabled:cursor-not-allowed hover:bg-slate-50"
            >
              Previous
            </button>
            <button
              onClick={() => onPageChange(pagination.current_page + 1)}
              disabled={pagination.current_page >= pagination.last_page}
              className="px-3 py-1.5 rounded border border-slate-200 text-slate-600 disabled:opacity-40 disabled:cursor-not-allowed hover:bg-slate-50"
            >
              Next
            </button>
          </div>
        </div>
      )}
    </div>
  );
}
