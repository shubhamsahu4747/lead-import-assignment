import React from 'react';
import { Database, CheckCircle, AlertTriangle, Layers, Clock } from 'lucide-react';

export default function ImportStats({ importData }) {
  if (!importData) return null;

  const {
    total_records = 0,
    processed_records = 0,
    success_count = 0,
    failed_count = 0,
    progress = 0,
    status = 'pending',
  } = importData;

  const formatNumber = (num) => new Intl.NumberFormat().format(num || 0);

  return (
    <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
      {/* Total Records */}
      <div className="bg-white p-4 rounded-xl border border-slate-200 shadow-sm flex items-center gap-3.5">
        <div className="p-2.5 rounded-lg bg-slate-100 text-slate-700">
          <Database className="w-5 h-5" />
        </div>
        <div>
          <p className="text-xs font-semibold text-slate-400 uppercase tracking-wider">Total Records</p>
          <p className="text-xl font-bold text-slate-900 mt-0.5">{formatNumber(total_records)}</p>
        </div>
      </div>

      {/* Processed Records */}
      <div className="bg-white p-4 rounded-xl border border-slate-200 shadow-sm flex items-center gap-3.5">
        <div className="p-2.5 rounded-lg bg-blue-50 text-blue-600">
          <Layers className="w-5 h-5" />
        </div>
        <div>
          <p className="text-xs font-semibold text-slate-400 uppercase tracking-wider">Processed</p>
          <p className="text-xl font-bold text-blue-600 mt-0.5">
            {formatNumber(processed_records)}
            <span className="text-xs text-slate-400 font-normal ml-1">({progress}%)</span>
          </p>
        </div>
      </div>

      {/* Successful Leads */}
      <div className="bg-white p-4 rounded-xl border border-slate-200 shadow-sm flex items-center gap-3.5">
        <div className="p-2.5 rounded-lg bg-green-50 text-green-600">
          <CheckCircle className="w-5 h-5" />
        </div>
        <div>
          <p className="text-xs font-semibold text-slate-400 uppercase tracking-wider">Successful</p>
          <p className="text-xl font-bold text-green-600 mt-0.5">{formatNumber(success_count)}</p>
        </div>
      </div>

      {/* Failed Records */}
      <div className="bg-white p-4 rounded-xl border border-slate-200 shadow-sm flex items-center gap-3.5">
        <div className={`p-2.5 rounded-lg ${failed_count > 0 ? 'bg-red-50 text-red-600' : 'bg-slate-100 text-slate-400'}`}>
          <AlertTriangle className="w-5 h-5" />
        </div>
        <div>
          <p className="text-xs font-semibold text-slate-400 uppercase tracking-wider">Failed / Duplicates</p>
          <p className={`text-xl font-bold mt-0.5 ${failed_count > 0 ? 'text-red-600' : 'text-slate-600'}`}>
            {formatNumber(failed_count)}
          </p>
        </div>
      </div>
    </div>
  );
}
