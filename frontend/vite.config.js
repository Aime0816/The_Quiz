import {defineConfig} from 'vite';
export default defineConfig({base:'./',build:{outDir:'../play',emptyOutDir:true},server:{proxy:{'/api': 'http://127.0.0.1:8000','/uploads':'http://127.0.0.1:8000','/login.php':'http://127.0.0.1:8000','/dashboard.php':'http://127.0.0.1:8000'}}});
