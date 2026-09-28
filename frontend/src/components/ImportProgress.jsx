import React from 'react';
import { Loader2, CheckCircle2, XCircle, Clock, Download, AlertOctagon, RotateCw, Eye } from 'lucide-react';
import { importApi } from '../services/importApi';
import ImportStats from './ImportStats';

export default function ImportProgress({
  importData,
  isPolling,
  onReset,
  onViewFailures,
}) {
  if (!importData) return null;

  const {
    id,
    original_filename,
    status = 'pending',
    total_records = 0,
    processed_records = 0,
    success_count = 0,
    failed_count = 0,
    progress = 0,
    error_message = null,
  } = importData;

  const formatNumber = (num) => new Intl.NumberFormat().format(num || 0);

  const getStatusBadge = () => {
    switch (status) {
      case 'processing':
        return (
          <span className="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold bg-blue-100 text-blue-700 animate-pulse">
            <Loader2 className="w-3.5 h-3.5 animate-spin" />
            Processing...
          </span>
        );
      case 'completed':
        return (
          <span className="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold bg-green-100 text-green-700">
            <CheckCircle2 className="w-3.5 h-3.5" />
            Completed
          </span>
        );
      case 'failed':
        return (
          <span className="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold bg-red-100 text-red-700">
            <XCircle className="w-3.5 h-3.5" />
            Failed
          </span>
        );
      case 'pending':
      default:
        return (
          <span className="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold bg-amber-100 text-amber-700">
            <Clock className="w-3.5 h-3.5" />
            Pending in Queue
          </span>
        );
    }
  };

  return (
    <div className="bg-white rounded-xl shadow-sm border border-slate-200 p-6 md:p-8">
      {/* Header */}
      <div className="flex flex-col sm:flex-row sm:items-center justify-between pb-6 border-b border-slate-100 gap-4">
        <div>
          <div className="flex items-center gap-3">
            <h2 className="text-xl font-bold text-slate-900">
              Import #{id}
            </h2>
            {getStatusBadge()}
          </div>
          <p className="text-sm text-slate-500 mt-1 font-mono">
            {original_filename}
          </p>
        </div>

        <div className="flex items-center gap-2">
          {onReset && (
            <button
              onClick={onReset}
              className="px-4 py-2 text-xs font-semibold text-slate-600 bg-slate-100 hover:bg-slate-200 rounded-lg transition-colors flex items-center gap-1.5"
            >
              <RotateCw className="w-3.5 h-3.5" />
              Import Another File
            </button>
          )}
        </div>
      </div>

      {/* Progress Bar Section */}
      <div className="my-6">
        <div className="flex justify-between items-center text-sm font-medium mb-2">
          <span className="text-slate-700">
            {formatNumber(processed_records)} / {formatNumber(total_records)} records processed
          </span>
          <span className="font-bold text-blue-600 text-base">{progress}%</span>
        </div>

        <div className="w-full bg-slate-100 rounded-full h-3 overflow-hidden shadow-inner">
          <div
            className={`h-3 rounded-full transition-all duration-500 ${
              status === 'completed'
                ? failed_count > 0
                  ? 'bg-amber-500'
                  : 'bg-green-600'
                : status === 'failed'
                ? 'bg-red-500'
                : 'bg-blue-600'
            }`}
            style={{ width: `${Math.max(3, progress)}%` }}
          />
        </div>
      </div>

      {/* Metric Cards */}
      <ImportStats importData={importData} />

      {/* Error Message if failed */}
      {status === 'failed' && error_message && (
        <div className="mt-6 p-4 bg-red-50 border border-red-200 rounded-lg flex items-start gap-3">
          <AlertOctagon className="w-5 h-5 text-red-600 shrink-0 mt-0.5" />
          <div>
            <h4 className="text-sm font-semibold text-red-800">Import Catastrophic Failure</h4>
            <p className="text-xs text-red-700 mt-1">{error_message}</p>
          </div>
        </div>
      )}

      {/* Actions and Failure Inspector if completed */}
      {status === 'completed' && (
        <div className="mt-6 pt-6 border-t border-slate-100 flex flex-wrap items-center justify-between gap-4">
          <div className="text-xs text-slate-500">
            {failed_count > 0 ? (
              <span className="text-amber-700 font-medium">
                {formatNumber(failed_count)} records failed validation or duplicate checks.
              </span>
            ) : (
              <span className="text-green-700 font-medium">
                All {formatNumber(success_count)} records were imported cleanly into MySQL.
              </span>
            )}
          </div>

          <div className="flex items-center gap-3">
            {failed_count > 0 && (
              <>
                {onViewFailures && (
                  <button
                    onClick={() => onViewFailures(id)}
                    className="px-4 py-2 text-xs font-semibold text-slate-700 bg-slate-100 hover:bg-slate-200 rounded-lg flex items-center gap-1.5 transition-colors"
                  >
                    <Eye className="w-3.5 h-3.5" />
                    Inspect Failures
                  </button>
                )}

                <a
                  href={importApi.getFailedCsvDownloadUrl(id)}
                  download={`failed_records_${id}.csv`}
                  className="px-4 py-2 text-xs font-semibold text-red-700 bg-red-50 hover:bg-red-100 border border-red-200 rounded-lg flex items-center gap-1.5 transition-colors"
                >
                  <Download className="w-3.5 h-3.5" />
                  Download failed_records.csv
                </a>
              </>
            )}
          </div>
        </div>
      )}
    </div>
  );
}
