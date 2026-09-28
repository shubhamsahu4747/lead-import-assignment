import React, { useState, useEffect } from 'react';
import { UploadCloud, History, Database, Activity, CheckCircle2, Server, Layers } from 'lucide-react';
import UploadPage from './pages/LeadImport/Upload';
import ImportDetails from './pages/LeadImport/ImportDetails';
import ImportTable from './components/ImportTable';
import LeadsExplorer from './components/LeadsExplorer';
import FailuresModal from './components/FailuresModal';
import { importApi } from './services/importApi';

export default function App() {
  const [activeTab, setActiveTab] = useState('import'); // 'import', 'history', 'leads'
  const [activeImportId, setActiveImportId] = useState(null);
  const [inspectFailuresId, setInspectFailuresId] = useState(null);

  // History state
  const [importsHistory, setImportsHistory] = useState([]);
  const [historyLoading, setHistoryLoading] = useState(false);
  const [historyPage, setHistoryPage] = useState(1);
  const [historyMeta, setHistoryMeta] = useState(null);

  const fetchHistory = (page = 1) => {
    setHistoryLoading(true);
    importApi.getImports(page, 10)
      .then((res) => {
        setImportsHistory(res.data || []);
        setHistoryMeta(res.meta || null);
      })
      .catch((err) => {
        console.error('Failed to load import history:', err);
      })
      .finally(() => {
        setHistoryLoading(false);
      });
  };

  useEffect(() => {
    if (activeTab === 'history') {
      fetchHistory(historyPage);
    }
  }, [activeTab, historyPage]);

  const handleUploadSuccess = (importRecord) => {
    setActiveImportId(importRecord.id);
    fetchHistory(1);
  };

  const handleSelectImportFromHistory = (id) => {
    setActiveImportId(id);
    setActiveTab('import');
  };

  const handleResetImport = () => {
    setActiveImportId(null);
  };

  return (
    <div className="min-h-screen flex flex-col bg-slate-50 font-sans">
      {/* Top Navbar */}
      <header className="bg-white border-b border-slate-200 sticky top-0 z-30">
        <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
          <div className="flex items-center justify-between h-16">
            {/* Logo and Title */}
            <div className="flex items-center gap-3">
              <div className="w-9 h-9 rounded-xl bg-gradient-to-tr from-blue-600 to-indigo-600 flex items-center justify-center text-white shadow-sm shadow-blue-500/30">
                <UploadCloud className="w-5 h-5" />
              </div>
              <div>
                <h1 className="text-base font-bold text-slate-900 tracking-tight leading-none">
                  Lead Import Module
                </h1>
                <p className="text-[11px] text-slate-400 mt-1 font-medium">
                  High-Performance Stream Processing • Laravel & Redis
                </p>
              </div>
            </div>

            {/* Navigation Tabs */}
            <nav className="flex items-center gap-1 bg-slate-100 p-1 rounded-xl">
              <button
                onClick={() => setActiveTab('import')}
                className={`px-3.5 py-1.5 rounded-lg text-xs font-semibold flex items-center gap-2 transition-all ${
                  activeTab === 'import'
                    ? 'bg-white text-blue-600 shadow-sm'
                    : 'text-slate-600 hover:text-slate-900 hover:bg-slate-200/50'
                }`}
              >
                <UploadCloud className="w-3.5 h-3.5" />
                <span>Import</span>
                {activeImportId && (
                  <span className="w-2 h-2 rounded-full bg-blue-600 animate-pulse" />
                )}
              </button>

              <button
                onClick={() => setActiveTab('history')}
                className={`px-3.5 py-1.5 rounded-lg text-xs font-semibold flex items-center gap-2 transition-all ${
                  activeTab === 'history'
                    ? 'bg-white text-blue-600 shadow-sm'
                    : 'text-slate-600 hover:text-slate-900 hover:bg-slate-200/50'
                }`}
              >
                <History className="w-3.5 h-3.5" />
                <span>History</span>
              </button>

              <button
                onClick={() => setActiveTab('leads')}
                className={`px-3.5 py-1.5 rounded-lg text-xs font-semibold flex items-center gap-2 transition-all ${
                  activeTab === 'leads'
                    ? 'bg-white text-blue-600 shadow-sm'
                    : 'text-slate-600 hover:text-slate-900 hover:bg-slate-200/50'
                }`}
              >
                <Database className="w-3.5 h-3.5" />
                <span>Leads Explorer</span>
              </button>
            </nav>
          </div>
        </div>
      </header>

      {/* Main Container */}
      <main className="flex-1 max-w-7xl w-full mx-auto px-4 sm:px-6 lg:px-8 py-8">
        {activeTab === 'import' && (
          <div>
            {activeImportId ? (
              <ImportDetails
                importId={activeImportId}
                onReset={handleResetImport}
                onViewFailures={(id) => setInspectFailuresId(id)}
              />
            ) : (
              <UploadPage onUploadSuccess={handleUploadSuccess} />
            )}
          </div>
        )}

        {activeTab === 'history' && (
          <div className="max-w-6xl mx-auto">
            <ImportTable
              imports={importsHistory}
              loading={historyLoading}
              onSelectImport={handleSelectImportFromHistory}
              onViewFailures={(id) => setInspectFailuresId(id)}
              pagination={historyMeta}
              onPageChange={(p) => setHistoryPage(p)}
            />
          </div>
        )}

        {activeTab === 'leads' && (
          <div className="max-w-6xl mx-auto">
            <LeadsExplorer />
          </div>
        )}
      </main>

      {/* Failure Inspector Modal */}
      {inspectFailuresId && (
        <FailuresModal
          importId={inspectFailuresId}
          onClose={() => setInspectFailuresId(null)}
        />
      )}

      {/* Footer */}
      <footer className="bg-white border-t border-slate-200 py-4 mt-auto">
        <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 flex flex-col sm:flex-row items-center justify-between text-xs text-slate-500 gap-2">
          <div className="flex items-center gap-2">
            <span className="w-2 h-2 rounded-full bg-green-500" />
            <span>Redis Queue & Workers Active</span>
            <span>•</span>
            <span>Batch Size: 5,000</span>
          </div>
          <div>
            Production-Grade Large CSV Lead Ingestion Architecture
          </div>
        </div>
      </footer>
    </div>
  );
}
