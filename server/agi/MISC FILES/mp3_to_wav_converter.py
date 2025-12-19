import tkinter as tk
from tkinter import filedialog, messagebox
import subprocess
import os

class MP3toWAVConverter:
    def __init__(self, root):
        self.root = root
        self.root.title("Audio to WAV Converter")
        self.root.geometry("450x280")
        self.root.configure(bg='#2b2b2b')
        self.root.resizable(False, False)

        # Title
        title = tk.Label(root, text="Audio to WAV Converter",
                        font=("Arial", 18, "bold"),
                        bg='#2b2b2b', fg='#4CAF50')
        title.pack(pady=25)

        # Info text
        info = tk.Label(root, text="8kHz • Mono • 16-bit PCM",
                       font=("Arial", 9),
                       bg='#2b2b2b', fg='#888888')
        info.pack()

        # Main frame
        main_frame = tk.Frame(root, bg='#2b2b2b')
        main_frame.pack(pady=30)

        # Select Audio button
        self.select_btn = tk.Button(main_frame,
                                    text="📁 Select Audio File",
                                    command=self.select_and_convert,
                                    font=("Arial", 12, "bold"),
                                    bg='#4CAF50', fg='white',
                                    activebackground='#45a049',
                                    activeforeground='white',
                                    relief='flat',
                                    cursor='hand2',
                                    padx=40, pady=15,
                                    borderwidth=0)
        self.select_btn.pack()

        # Status label
        self.status_label = tk.Label(root, text="Ready to convert",
                                     font=("Arial", 9),
                                     bg='#2b2b2b', fg='#888888',
                                     wraplength=400)
        self.status_label.pack(pady=20)

        # Footer
        footer = tk.Label(root, text="Powered by FFmpeg",
                         font=("Arial", 8),
                         bg='#2b2b2b', fg='#555555')
        footer.pack(side='bottom', pady=10)

    def select_and_convert(self):
        # Select audio file
        input_file = filedialog.askopenfilename(
            title="Select audio file",
            filetypes=[("Audio files", "*.mp3 *.wav"), ("MP3 files", "*.mp3"), ("WAV files", "*.wav"), ("All files", "*.*")]
        )

        if not input_file:
            return

        # Ask where to save
        default_name = os.path.splitext(os.path.basename(input_file))[0] + ".wav"
        output_file = filedialog.asksaveasfilename(
            title="Save WAV file as",
            defaultextension=".wav",
            initialfile=default_name,
            filetypes=[("WAV files", "*.wav"), ("All files", "*.*")]
        )

        if not output_file:
            return

        self.status_label.config(text="Converting... Please wait", fg='#FFA726')
        self.select_btn.config(state='disabled')
        self.root.update()

        try:
            # Convert using ffmpeg
            # Sample rate: 8000 Hz, Channels: mono, Format: PCM 16-bit
            result = subprocess.run([
                'ffmpeg', '-i', input_file,
                '-ar', '8000',
                '-ac', '1',
                '-sample_fmt', 's16',
                output_file, '-y'
            ], capture_output=True, text=True)

            if result.returncode == 0:
                self.status_label.config(
                    text=f"✓ Success! Saved to: {os.path.basename(output_file)}",
                    fg='#4CAF50'
                )
                messagebox.showinfo("Success",
                                  f"Conversion complete!\n\nSaved to:\n{output_file}")
            else:
                raise Exception("FFmpeg conversion failed")

        except FileNotFoundError:
            self.status_label.config(text="✗ Error: FFmpeg not found", fg='#f44336')
            messagebox.showerror("Error",
                               "FFmpeg not found!\n\nPlease make sure FFmpeg is installed and in your PATH.")
        except Exception as e:
            self.status_label.config(text="✗ Conversion failed", fg='#f44336')
            messagebox.showerror("Error", f"Conversion failed:\n{str(e)}")
        finally:
            self.select_btn.config(state='normal')

if __name__ == "__main__":
    root = tk.Tk()
    app = MP3toWAVConverter(root)
    root.mainloop()
