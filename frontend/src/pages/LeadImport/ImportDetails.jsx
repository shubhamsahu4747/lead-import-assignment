import React from 'react';
import { useImportStatus } from '../../hooks/useImportStatus';
import ImportProgress from '../../components/ImportProgress';
import { Loader2 } from 'lucide-react';

export default function ImportDetails({ importId, onReset, onViewFailures }) {
  const { importData, loading, error, isPolling } = useImportStatus(importId, 2500);

  if (loading && !importData) {
    return (
      <div className="bg-white rounded-xl shadow-sm border border-slate-200 p-12 text-center text-slate-400">
        <Loader2 className="w-8 h-8 animate-spin mx-auto text-blue-600 mb-3" />
        <p className="text-sm font-medium">Connecting to import #{importId}...</p>
      </div>
    );
  }

  if (error && !importData) {
    return (
      <div className="bg-red-50 border border-red-200 rounded-xl p-6 text-red-800 text-sm">
        <p className="font-semibold">Failed to fetch import status</p>
        <p className="mt-1">{error}</p>
        <button
          onClick={onReset}
          className="mt-4 px-4 py-2 bg-red-600 text-white rounded-lg text-xs font-semibold hover:bg-red-700"
        >
          Back to Upload
        </button>
      </div>
    );
  }

  return (
    <div className="max-w-4xl mx-auto space-y-6">
      <ImportProgress
        importData={importData}
        isPolling={isPolling}
        onReset={onReset}
        onViewFailures={onViewFailures}
      />
    </div>
  );
}
