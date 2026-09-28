import React from 'react';
import CsvUploader from '../../components/CsvUploader';

export default function UploadPage({ onUploadSuccess }) {
  return (
    <div className="max-w-4xl mx-auto space-y-6">
      <CsvUploader onUploadSuccess={onUploadSuccess} />
    </div>
  );
}
