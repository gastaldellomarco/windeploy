// Path: frontend/src/App.jsx
import React from "react";
import { Toaster } from "react-hot-toast";
import AppRouter from "./router/index.jsx";

export default function App() {
  return (
    <div className="min-h-screen" style={{ backgroundColor: "#F0F4F8" }}>
      <AppRouter />
      <Toaster
        position="top-right"
        toastOptions={{
          duration: 4000,
          
        }}
      />
    </div>
  );
}
