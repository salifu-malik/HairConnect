export const applyWatermark = (file: File): Promise<Blob> => {
  return new Promise((resolve, reject) => {
    const reader = new FileReader();
    reader.readAsDataURL(file);
    reader.onload = (event) => {
      const img = new Image();
      img.src = event.target?.result as string;
      img.onload = () => {
        const canvas = document.createElement('canvas');
        canvas.width = img.width;
        canvas.height = img.height;
        const ctx = canvas.getContext('2d');
        if (!ctx) return reject('Canvas context not found');

        ctx.drawImage(img, 0, 0);
        ctx.font = 'bold 30px Montserrat';
        ctx.fillStyle = 'rgba(255, 255, 255, 0.5)';
        ctx.fillText('Hairconnect', 20, img.height - 30);

        canvas.toBlob((blob) => {
          if (blob) resolve(blob);
          else reject('Watermark failed');
        }, file.type);
      };
    };
    reader.onerror = reject;
  });
};
