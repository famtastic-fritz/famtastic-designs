"""Use an already installed offline system voice; recorded narration is preferred."""
import platform,shutil,subprocess,tempfile
from pathlib import Path

def voice(text_path,output_path,voice_name=None):
    text_path=Path(text_path).resolve();output_path=Path(output_path).resolve()
    if platform.system()!='Darwin':raise RuntimeError('This voice adapter requires macOS say. Import recorded WAV on other systems; no online fallback.')
    if not text_path.is_file() or not text_path.read_text(encoding='utf-8').strip():raise ValueError('A nonempty UTF-8 script file is required')
    if output_path.exists():raise ValueError('Voice output already exists; choose a new version')
    if output_path.suffix.lower()!='.wav':raise ValueError('Voice output must be .wav')
    if not shutil.which('say') or not shutil.which('ffmpeg'):raise RuntimeError('Installed say and ffmpeg are required')
    output_path.parent.mkdir(parents=True,exist_ok=True)
    with tempfile.TemporaryDirectory(prefix='voice-') as tmp:
        aiff=Path(tmp)/'speech.aiff';cmd=['say','-f',str(text_path),'-o',str(aiff)]
        if voice_name:cmd+=['-v',voice_name]
        r=subprocess.run(cmd,capture_output=True,text=True,timeout=600)
        if r.returncode:raise RuntimeError('Installed system voice failed: '+r.stderr[:400])
        cmd=['ffmpeg','-v','error','-nostdin','-i',str(aiff),'-ar','48000','-ac','1',str(output_path)]
        r=subprocess.run(cmd,capture_output=True,text=True,timeout=120)
        if r.returncode:raise RuntimeError('Voice WAV conversion failed: '+r.stderr[:400])
    return {'path':str(output_path),'provider':'macos_say','model':'system_voice','voice':voice_name or 'system_default','provider_fee_usd':0,'review':'listen_and_adjust_pacing','note':'Requires an already downloaded voice; no voice clone or cloud request.'}
