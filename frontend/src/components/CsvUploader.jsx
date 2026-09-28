import React, { useState, useRef } from 'react';
import { UploadCloud, FileText, CheckCircle2, AlertCircle, Loader2, Download, Mail } from 'lucide-react';
import { importApi } from '../services/importApi';

export default function CsvUploader({ onUploadSuccess }) {
  const [file, setFile] = useState(null);
  const [notificationEmail, setNotificationEmail] = useState('');
  const [isDragging, setIsDragging] = useState(false);
  const [isUploading, setIsUploading] = useState(false);
  const [uploadProgress, setUploadProgress] = useState(0);
  const [errorMessage, setErrorMessage] = useState(null);
  const fileInputRef = useRef(null);

  const formatFileSize = (bytes) => {
    if (bytes === 0) return '0 Bytes';
    const k = 1024;
    const sizes = ['Bytes', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
  };

  const handleDragOver = (e) => {
    e.preventDefault();
    setIsDragging(true);
  };

  const handleDragLeave = (e) => {
    e.preventDefault();
    setIsDragging(false);
  };

  const handleDrop = (e) => {
    e.preventDefault();
    setIsDragging(false);
    setErrorMessage(null);

    const droppedFiles = e.dataTransfer.files;
    if (droppedFiles && droppedFiles.length > 0) {
      validateAndSetFile(droppedFiles[0]);
    }
  };

  const handleFileChange = (e) => {
    setErrorMessage(null);
    if (e.target.files && e.target.files.length > 0) {
      validateAndSetFile(e.target.files[0]);
    }
  };

  const validateAndSetFile = (selectedFile) => {
    const validExtensions = ['.csv', '.txt'];
    const isCsv = validExtensions.some(ext => selectedFile.name.toLowerCase().endsWith(ext));

    if (!isCsv) {
      setErrorMessage('Please select a valid CSV file (.csv format).');
      return;
    }

    if (selectedFile.size > 100 * 1024 * 1024) {
      setErrorMessage('File size exceeds the 100 MB limit.');
      return;
    }

    setFile(selectedFile);
  };

  const handleUpload = async () => {
    if (!file) return;

    setIsUploading(true);
    setErrorMessage(null);
    setUploadProgress(0);

    try {
      const response = await importApi.uploadCsv(
        file,
        notificationEmail,
        (progressEvent) => {
          if (progressEvent.total) {
            const percentCompleted = Math.round((progressEvent.loaded * 100) / progressEvent.total);
            setUploadProgress(percentCompleted);
          }
        }
      );

      if (onUploadSuccess && response.import) {
        onUploadSuccess(response.import);
      }
    } catch (err) {
      setErrorMessage(err.message || 'Failed to upload CSV file.');
    } finally {
      setIsUploading(false);
    }
  };

  return (
    <div className="bg-white rounded-xl shadow-sm border border-slate-200 p-6 md:p-8">
      <div className="mb-6">
        <h2 className="text-xl font-bold text-slate-900 tracking-tight">Upload Leads CSV</h2>
        <p className="text-sm text-slate-500 mt-1">
          Streamed high-volume ingestion. Supports 100K+ records with deduplication, atomic progress, and zero memory exhaustion.
        </p>
      </div>

      {/* Error Alert */}
      {errorMessage && (
        <div className="mb-6 p-4 bg-red-50 border-l-4 border-red-500 rounded-r-lg flex items-start gap-3">
          <AlertCircle className="w-5 h-5 text-red-600 mt-0.5 shrink-0" />
          <div className="text-sm text-red-800 font-medium">
            {errorMessage}
          </div>
        </div>
      )}

      {/* Drag & Drop Area */}
      <div
        onDragOver={handleDragOver}
        onDragLeave={handleDragLeave}
        onDrop={handleDrop}
        onClick={() => !isUploading && fileInputRef.current?.click()}
        className={`border-2 border-dashed rounded-xl p-8 text-center cursor-pointer transition-all duration-200 ${
          isDragging
            ? 'border-blue-500 bg-blue-50/60 scale-[0.99]'
            : file
            ? 'border-green-400 bg-green-50/20'
            : 'border-slate-300 hover:border-blue-400 hover:bg-slate-50/60'
        }`}
      >
        <input
          ref={fileInputRef}
          type="file"
          accept=".csv,text/csv,text/plain"
          onChange={handleFileChange}
          className="hidden"
          disabled={isUploading}
        />

        <div className="flex flex-col items-center justify-center">
          <div className={`p-4 rounded-full mb-3 ${file ? 'bg-green-100 text-green-600' : 'bg-blue-100 text-blue-600'}`}>
            {file ? <FileText className="w-8 h-8" /> : <UploadCloud className="w-8 h-8" />}
          </div>

          {file ? (
            <div>
              <p className="text-base font-semibold text-slate-900">{file.name}</p>
              <p className="text-xs text-slate-500 mt-1">{formatFileSize(file.size)}</p>
              <span className="inline-block mt-2 text-xs font-medium text-blue-600 hover:underline">
                Click or drop another file to replace
              </span>
            </div>
          ) : (
            <div>
              <p className="text-base font-semibold text-slate-800">
                Drop your CSV file here, or <span className="text-blue-600 font-medium">browse</span>
              </p>
              <p className="text-xs text-slate-400 mt-1">
                CSV headers required: <code className="text-slate-600 bg-slate-100 px-1.5 py-0.5 rounded font-mono">name</code>, <code className="text-slate-600 bg-slate-100 px-1.5 py-0.5 rounded font-mono">email</code>, <code className="text-slate-600 bg-slate-100 px-1.5 py-0.5 rounded font-mono">phone</code>, <code className="text-slate-600 bg-slate-100 px-1.5 py-0.5 rounded font-mono">company</code>
              </p>
              <p className="text-xs text-slate-400 mt-0.5">Maximum file size: 100 MB</p>
            </div>
          )}
        </div>
      </div>

      {/* Optional Email Notification */}
      <div className="mt-5">
        <label className="block text-xs font-semibold uppercase tracking-wider text-slate-600 mb-1">
          Notification Email (Optional)
        </label>
        <div className="relative">
          <Mail className="w-4 h-4 text-slate-400 absolute left-3 top-3 pointer-events-none" />
          <input
            type="email"
            placeholder="notifications@yourcompany.com"
            value={notificationEmail}
            onChange={(e) => setNotificationEmail(e.target.value)}
            disabled={isUploading}
            className="w-full pl-9 pr-4 py-2.5 text-sm bg-slate-50 border border-slate-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 transition-colors"
          />
        </div>
        <p className="text-xs text-slate-400 mt-1">
          A summary email will be sent automatically upon completion with success/failure statistics and the error CSV.
        </p>
      </div>

      {/* Upload Progress Bar (during HTTP transfer) */}
      {isUploading && (
        <div className="mt-6">
          <div className="flex justify-between text-xs font-medium text-slate-600 mb-1.5">
            <span>Uploading to server...</span>
            <span>{uploadProgress}%</span>
          </div>
          <div className="w-full bg-slate-100 rounded-full h-2 overflow-hidden">
            <div
              className="bg-blue-600 h-2 rounded-full transition-all duration-300"
              style={{ width: `${uploadProgress}%` }}
            />
          </div>
        </div>
      )}

      {/* Action Buttons */}
      <div className="mt-6 flex flex-col sm:flex-row items-center justify-between gap-4 pt-4 border-t border-slate-100">
        {/* Sample CSV Download Buttons */}
        <div className="flex items-center gap-2 text-xs text-slate-500">
          <Download className="w-3.5 h-3.5 text-slate-400" />
          <span>Need test data?</span>
          <a
            href={importApi.getSampleCsvUrl(100)}
            download="sample-leads-100.csv"
            className="text-blue-600 hover:text-blue-800 font-medium underline"
          >
            100 rows
          </a>
          <span>•</span>
          <a
            href={importApi.getSampleCsvUrl(1000)}
            download="sample-leads-1000.csv"
            className="text-blue-600 hover:text-blue-800 font-medium underline"
          >
            1,000 rows
          </a>
          <span>•</span>
          <a
            href={importApi.getSampleCsvUrl(10000)}
            download="sample-leads-10000.csv"
            className="text-blue-600 hover:text-blue-800 font-medium underline"
          >
            10,000 rows
          </a>
        </div>

        <button
          onClick={handleUpload}
          disabled={!file || isUploading}
          className={`w-full sm:w-auto px-6 py-2.5 rounded-lg text-sm font-semibold flex items-center justify-center gap-2 transition-all ${
            !file || isUploading
              ? 'bg-slate-100 text-slate-400 cursor-not-allowed'
              : 'bg-blue-600 text-white hover:bg-blue-700 active:scale-[0.98] shadow-sm shadow-blue-500/20'
          }`}
        >
          {isUploading ? (
            <>
              <Loader2 className="w-4 h-4 animate-spin" />
              <span>Uploading & Dispatching...</span>
            </>
          ) : (
            <>
              <CheckCircle2 className="w-4 h-4" />
              <span>Start Asynchronous Import</span>
            </>
          )}
        </button>
      </div>
    </div>
  );
}
