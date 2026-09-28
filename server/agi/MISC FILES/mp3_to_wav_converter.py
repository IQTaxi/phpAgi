"""Audio to 8 kHz mono 16-bit WAV converter for Asterisk prompts.

Usage:
    python mp3_to_wav_converter.py [input_file] [output_file_or_folder]

Both arguments are optional and only prefill the fields in the window.
"""
import tkinter as tk
from tkinter import filedialog, messagebox
import subprocess
import os
import sys
import math
import wave
import tempfile
from array import array

# Phone enhancement: cut the bass a phone line drops anyway, boost the 1-3 kHz band
# that carries the words, set a fixed telephony speech level, and add lead silence
# so the call audio settles before the first word.
PHONE_EQ = ("highpass=f=220:p=2,highpass=f=220:p=2,"
            "equalizer=f=1600:t=o:w=1.5:g=8,"
            "equalizer=f=2600:t=o:w=1:g=4")
TARGET_SPEECH_DB = -22.0
MAX_PEAK_DB = -4.0
LEAD_SILENCE_MS = 1000

BG = '#2b2b2b'
FIELD_BG = '#3c3c3c'


def run_ffmpeg(args):
    result = subprocess.run(['ffmpeg', '-y', '-hide_banner', '-loglevel', 'error'] + args,
                            capture_output=True, text=True)
    if result.returncode != 0:
        raise Exception("FFmpeg failed:\n" + result.stderr[-500:])


def convert_plain(input_file, output_file):
    """Plain conversion: 8000 Hz, mono, PCM 16-bit. Sound is left as is."""
    run_ffmpeg(['-i', input_file, '-ar', '8000', '-ac', '1', '-sample_fmt', 's16', output_file])


def measure_speech_and_peak(path):
    """Speech level in dB (pauses ignored) and peak in dB of a 16-bit mono wav."""
    with wave.open(path, 'rb') as w:
        samples = array('h', w.readframes(w.getnframes()))
    if sys.byteorder == 'big':
        samples.byteswap()
    if not samples:
        return None, None

    frame = 160  # 20 ms at 8 kHz
    full_scale = 32768.0 ** 2
    speech_gate = 10 ** (-45 / 10)
    speech_powers = []
    for i in range(0, len(samples) - frame + 1, frame):
        power = sum(s * s for s in samples[i:i + frame]) / frame / full_scale
        if power > speech_gate:
            speech_powers.append(power)

    peak = max(abs(s) for s in samples)
    peak_db = 20 * math.log10(peak / 32768.0) if peak else None
    if not speech_powers:
        return None, peak_db
    return 10 * math.log10(sum(speech_powers) / len(speech_powers)), peak_db


def make_phone_version(plain_wav, enhanced_wav):
    """Build the phone-enhanced copy from the plain 8 kHz conversion."""
    fd, eq_only = tempfile.mkstemp(suffix='.wav')
    os.close(fd)
    try:
        # Pass 1: EQ only, to measure the level the gain has to work from
        run_ffmpeg(['-i', plain_wav, '-af', PHONE_EQ,
                    '-ar', '8000', '-ac', '1', '-c:a', 'pcm_s16le', eq_only])
        speech_db, peak_db = measure_speech_and_peak(eq_only)
    finally:
        os.remove(eq_only)

    gain = 0.0
    if speech_db is not None and peak_db is not None:
        gain = min(TARGET_SPEECH_DB - speech_db, MAX_PEAK_DB - peak_db)
        gain = max(-20.0, min(20.0, gain))

    # Pass 2: EQ + fixed gain + lead silence
    run_ffmpeg(['-i', plain_wav,
                '-af', f"{PHONE_EQ},volume={gain:.2f}dB,adelay={LEAD_SILENCE_MS}:all=1",
                '-ar', '8000', '-ac', '1', '-c:a', 'pcm_s16le',
                '-map_metadata', '-1', '-fflags', '+bitexact', enhanced_wav])
    return gain


def default_output_for(input_file):
    """Same folder and name as the input, as .wav. A 'converted' subfolder if that is the input itself."""
    folder = os.path.dirname(os.path.abspath(input_file))
    name = os.path.splitext(os.path.basename(input_file))[0] + ".wav"
    output_file = os.path.join(folder, name)
    if os.path.normcase(output_file) == os.path.normcase(os.path.abspath(input_file)):
        output_file = os.path.join(folder, "converted", name)
    return output_file


def resolve_output(input_file, output_text):
    """Turn the output field into a file path. Empty = default, a folder = input name inside it."""
    output_text = output_text.strip().strip('"')
    if not output_text:
        return default_output_for(input_file)
    name = os.path.splitext(os.path.basename(input_file))[0] + ".wav"
    if os.path.isdir(output_text) or output_text.endswith(('/', '\\')):
        return os.path.join(os.path.abspath(output_text), name)
    if not os.path.splitext(output_text)[1]:
        output_text += ".wav"
    return os.path.abspath(output_text)


class MP3toWAVConverter:
    def __init__(self, root, input_file='', output_file=''):
        self.root = root
        self.root.title("Audio to WAV Converter")
        self.root.geometry("600x380")
        self.root.configure(bg=BG)
        self.root.resizable(False, False)

        self.input_var = tk.StringVar(value=input_file)
        self.output_var = tk.StringVar(value=output_file)
        self.enhance_var = tk.BooleanVar(value=True)
        self.auto_output = ''

        # Title
        title = tk.Label(root, text="Audio to WAV Converter",
                        font=("Arial", 18, "bold"),
                        bg=BG, fg='#4CAF50')
        title.pack(pady=(20, 5))

        # Info text
        info = tk.Label(root, text="8kHz • Mono • 16-bit PCM",
                       font=("Arial", 9),
                       bg=BG, fg='#888888')
        info.pack()

        # Path fields
        fields = tk.Frame(root, bg=BG)
        fields.pack(padx=20, pady=(20, 5), fill='x')
        fields.columnconfigure(1, weight=1)
        self.add_path_row(fields, 0, "Input", self.input_var, self.browse_input)
        self.add_path_row(fields, 1, "Output", self.output_var, self.browse_output)
        tk.Label(fields, text="Output can be a file or a folder. Empty = next to the input.",
                 font=("Arial", 8), bg=BG, fg='#666666').grid(row=2, column=1, sticky='w')

        # Enhanced copy option
        tk.Checkbutton(root, text="Also make phone-enhanced copy (same name, 'enhanced' subfolder)",
                       variable=self.enhance_var,
                       font=("Arial", 9), bg=BG, fg='#cccccc',
                       selectcolor=BG, activebackground=BG, activeforeground='white'
                       ).pack(pady=(5, 10))

        # Convert button
        self.convert_btn = tk.Button(root,
                                     text="Convert",
                                     command=self.convert,
                                     font=("Arial", 12, "bold"),
                                     bg='#4CAF50', fg='white',
                                     activebackground='#45a049',
                                     activeforeground='white',
                                     relief='flat',
                                     cursor='hand2',
                                     padx=40, pady=10,
                                     borderwidth=0)
        self.convert_btn.pack()
        root.bind('<Return>', lambda event: self.convert())

        # Status label
        self.status_label = tk.Label(root, text="Ready to convert",
                                     font=("Arial", 9),
                                     bg=BG, fg='#888888',
                                     wraplength=550)
        self.status_label.pack(pady=15)

        # Footer
        footer = tk.Label(root, text="Powered by FFmpeg",
                         font=("Arial", 8),
                         bg=BG, fg='#555555')
        footer.pack(side='bottom', pady=10)

        if input_file and not output_file:
            self.set_auto_output(input_file)

    def add_path_row(self, parent, row, label, variable, browse_command):
        tk.Label(parent, text=label, font=("Arial", 10), bg=BG, fg='#cccccc',
                 width=7, anchor='w').grid(row=row, column=0, sticky='w', pady=4)
        tk.Entry(parent, textvariable=variable, font=("Arial", 10),
                 bg=FIELD_BG, fg='white', insertbackground='white',
                 relief='flat').grid(row=row, column=1, sticky='ew', padx=8, ipady=4)
        tk.Button(parent, text="Browse...", command=browse_command,
                  font=("Arial", 9), bg=FIELD_BG, fg='white',
                  activebackground='#505050', activeforeground='white',
                  relief='flat', cursor='hand2', padx=10
                  ).grid(row=row, column=2, sticky='e')

    def set_auto_output(self, input_file):
        # Only fill the output when the user has not typed their own
        current = self.output_var.get().strip()
        if not current or current == self.auto_output:
            self.auto_output = default_output_for(input_file)
            self.output_var.set(self.auto_output)

    def browse_input(self):
        current = self.input_var.get().strip()
        input_file = filedialog.askopenfilename(
            title="Select audio file",
            initialdir=os.path.dirname(current) if current else None,
            filetypes=[("Audio files", "*.mp3 *.wav *.m4a *.ogg *.flac *.aac"), ("MP3 files", "*.mp3"),
                       ("WAV files", "*.wav"), ("All files", "*.*")]
        )
        if input_file:
            input_file = os.path.normpath(input_file)
            self.input_var.set(input_file)
            self.set_auto_output(input_file)

    def browse_output(self):
        input_file = self.input_var.get().strip()
        current = self.output_var.get().strip() or (default_output_for(input_file) if input_file else '')
        output_file = filedialog.asksaveasfilename(
            title="Save WAV file as",
            defaultextension=".wav",
            initialdir=os.path.dirname(current) if current else None,
            initialfile=os.path.basename(current) if current else '',
            filetypes=[("WAV files", "*.wav"), ("All files", "*.*")]
        )
        if output_file:
            self.output_var.set(os.path.normpath(output_file))

    def convert(self):
        input_file = self.input_var.get().strip().strip('"')
        if not input_file or not os.path.isfile(input_file):
            messagebox.showerror("Error", "Select an input audio file first.")
            return

        output_file = resolve_output(input_file, self.output_var.get())
        if os.path.normcase(output_file) == os.path.normcase(os.path.abspath(input_file)):
            messagebox.showerror("Error", "Output would overwrite the input. Pick another name or folder.")
            return

        # Enhanced copy keeps the same filename so it can be uploaded as is
        enhance = self.enhance_var.get()
        enhanced_file = os.path.join(os.path.dirname(output_file), "enhanced", os.path.basename(output_file))

        existing = [p for p in [output_file] + ([enhanced_file] if enhance else []) if os.path.exists(p)]
        if existing and not messagebox.askyesno("Overwrite?", "Already exists:\n\n" + "\n".join(existing) +
                                                "\n\nOverwrite?"):
            return

        self.status_label.config(text="Converting... Please wait", fg='#FFA726')
        self.convert_btn.config(state='disabled')
        self.root.update()

        try:
            os.makedirs(os.path.dirname(output_file), exist_ok=True)
            convert_plain(input_file, output_file)
            message = f"Conversion complete!\n\nOriginal:\n{output_file}"

            if enhance:
                self.status_label.config(text="Making phone-enhanced copy...", fg='#FFA726')
                self.root.update()
                os.makedirs(os.path.dirname(enhanced_file), exist_ok=True)
                gain = make_phone_version(output_file, enhanced_file)
                message += (f"\n\nPhone-enhanced ({gain:+.1f} dB, {LEAD_SILENCE_MS} ms lead silence):"
                            f"\n{enhanced_file}")

            self.output_var.set(output_file)
            self.status_label.config(
                text=f"✓ Success! Saved {os.path.basename(output_file)}" + (" + enhanced copy" if enhance else ""),
                fg='#4CAF50'
            )
            messagebox.showinfo("Success", message)

        except FileNotFoundError:
            self.status_label.config(text="✗ Error: FFmpeg not found", fg='#f44336')
            messagebox.showerror("Error",
                               "FFmpeg not found!\n\nPlease make sure FFmpeg is installed and in your PATH.")
        except Exception as e:
            self.status_label.config(text="✗ Conversion failed", fg='#f44336')
            messagebox.showerror("Error", f"Conversion failed:\n{str(e)}")
        finally:
            self.convert_btn.config(state='normal')


if __name__ == "__main__":
    args = sys.argv[1:]
    root = tk.Tk()
    app = MP3toWAVConverter(root,
                            input_file=os.path.normpath(args[0]) if len(args) > 0 else '',
                            output_file=args[1] if len(args) > 1 else '')
    root.mainloop()
